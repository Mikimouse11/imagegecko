'use strict'

const {
  jsonResponse,
  errorResponse,
  getBody,
  getHeader,
  dbExecute,
  ApiError,
  callGemini
} = require('helper')

const MODEL = 'gemini-3-pro-image-preview'
const DEFAULT_OUTPUT_MIME = 'image/webp'
const GEMINI_IMAGE_CONFIG = {
  imageGenerationConfig: {
    numberOfImages: 1,
    outputFormat: 'webp'
  }
}

exports.handler = async (event) => {
  try {
    const { prompt, image, apiKey } = parseInput(event)
    const { id: creditRecordId, remainingCredits: availableCredits, email } = await checkCredits(apiKey)
    
    console.log('Image generation request received', {
      prompt: prompt?.substring(0, 100),
      hasImage: !!image?.base64,
      imageSize: image?.base64?.length,
      mimeType: image?.mimeType,
      email,
      creditsBefore: availableCredits
    })

    console.log('Starting image generation', { status: 'in_progress', creditsBefore: availableCredits })
    const result = await generateImage(prompt, image)
    console.log('Image generation completed', { status: 'success', hasResult: !!result?.imageBase64 })

    const remainingCredits = await consumeCredit(creditRecordId, availableCredits)
    console.log('Credit consumed', { creditsAfter: remainingCredits, creditsBefore: availableCredits })

    return jsonResponse({
      ...result,
      remainingCredits
    })
  } catch (err) {
    console.error('Error generating image:', err)
    return errorResponse(err)
  }
}

const parseInput = (event) => {
  const body = getBody(event) || {}
  const { prompt, image } = body

  if (!prompt) throw new ApiError('Missing prompt', 400)
  if (!image || !image.base64) throw new ApiError('Missing image', 400)

  const authHeader = (getHeader(event, 'authorization', '') || '').trim()
  if (!authHeader.toLowerCase().startsWith('bearer ')) throw new ApiError('Missing API key', 401)

  const apiKey = authHeader.slice(7).trim()
  if (!apiKey) throw new ApiError('Missing API key', 401)

  return {
    prompt,
    image: {
      base64: image.base64,
      mimeType: image.mime_type || image.mimeType || 'image/jpeg'
    },
    apiKey
  }
}

const checkCredits = async (apiKey) => {
  const [record] = await dbExecute(`
    SELECT
      id,
      email,
      IFNULL(remaining_credits, 0) AS remainingCredits
    FROM imagegecko_wp_keys
    WHERE api_key = :apiKey
    LIMIT 1
  `, {
    apiKey
  })

  if (!record?.id) throw new ApiError('Invalid API key', 401)

  const remainingCredits = Number(record.remainingCredits)

  if (!Number.isFinite(remainingCredits) || remainingCredits <= 0) throw new ApiError('No credits remaining', 402)

  return {
    id: record.id,
    email: record.email,
    remainingCredits
  }
}

const generateImage = async (promptText, image) => {
  console.log('Generating image with Gemini', { 
    status: 'starting',
    hasPrompt: !!promptText, 
    hasImage: !!image.base64,
    model: MODEL
  })

  const imageBase64 = image.base64.replace(/\s/g, '')
  const mimeType = image.mimeType || 'image/jpeg'

  const promptArray = [
    { text: promptText },
    {
      inlineData: {
        mimeType,
        data: imageBase64
      }
    }
  ]

  const response = await callGemini({
    model: MODEL,
    contents: promptArray,
    config: GEMINI_IMAGE_CONFIG
  })

  console.log('Gemini API call completed', { status: 'api_response_received' })

  const candidate = response.candidates?.[0]
  if (!candidate?.content?.parts) {
    console.error('Gemini response validation failed', { status: 'failed', reason: 'no_candidate_parts' })
    throw new Error('No candidate parts found in Gemini response')
  }

  for (const part of candidate.content.parts) {
    if (part.inlineData?.data) {
      console.log('Image extracted from Gemini response', { 
        status: 'success',
        hasImageData: !!part.inlineData.data,
        outputMimeType: part.inlineData.mimeType || DEFAULT_OUTPUT_MIME
      })
      return {
        imageBase64: part.inlineData.data.replace(/\s/g, ''),
        mimeType: DEFAULT_OUTPUT_MIME,
        model: MODEL
      }
    }
  }

  console.error('Image extraction failed', { status: 'failed', reason: 'no_image_data_in_response' })
  throw new Error('No image data found in Gemini response')
}

const consumeCredit = async (id, previousRemainingCredits) => {
  await dbExecute(`
    UPDATE imagegecko_wp_keys
    SET remaining_credits = GREATEST(IFNULL(remaining_credits, 0) - 1, 0)
    WHERE id = :id
  `, {
    id
  })

  const [updatedRecord] = await dbExecute(`
    SELECT IFNULL(remaining_credits, 0) AS remainingCredits
    FROM imagegecko_wp_keys
    WHERE id = :id
  `, {
    id
  })

  const latestCredits = Number(updatedRecord?.remainingCredits)
  const fallback = Number.isFinite(previousRemainingCredits)
    ? Math.max(Number(previousRemainingCredits) - 1, 0)
    : 0

  return Number.isFinite(latestCredits)
    ? latestCredits
    : fallback
}

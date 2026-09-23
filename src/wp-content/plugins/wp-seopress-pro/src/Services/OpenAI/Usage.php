<?php

namespace SEOPressPro\Services\OpenAI;

defined( 'ABSPATH' ) || exit;

class Usage {
	public const NAME_SERVICE                  = 'Usage';
	private const OPENAI_URL_USAGE             = 'https://api.openai.com/v1/usage';
	private const OPENAI_URL_CHAT_COMPLETIONS  = 'https://api.openai.com/v1/chat/completions';
	private const OPENAI_URL_RESPONSES         = 'https://api.openai.com/v1/responses';
	private const DEEPSEEK_URL_BALANCE         = 'https://api.deepseek.com/user/balance';
	private const DEEPSEEK_URL_COMPLETIONS     = 'https://api.deepseek.com/beta/completions';
	private const GEMINI_URL_GENERATE_CONTENT  = 'https://generativelanguage.googleapis.com/v1beta/models/';
	private const MISTRAL_URL_CHAT_COMPLETIONS = 'https://api.mistral.ai/v1/chat/completions';
	private const CLAUDE_URL_MESSAGES          = 'https://api.anthropic.com/v1/messages';

	private function getProviderEndpoints( $provider ) {
		$endpoints = array();

		// Sanitize provider parameter
		$provider = sanitize_text_field( strtolower( $provider ) );

		switch ( $provider ) {
			case 'openai':
				$endpoints['usage']            = self::OPENAI_URL_USAGE;
				$endpoints['chat_completions'] = self::OPENAI_URL_CHAT_COMPLETIONS;
				$endpoints['responses']        = self::OPENAI_URL_RESPONSES;
				break;
			case 'deepseek':
				$endpoints['balance']     = self::DEEPSEEK_URL_BALANCE;
				$endpoints['completions'] = self::DEEPSEEK_URL_COMPLETIONS;
				break;
			case 'gemini':
				$endpoints['generate_content'] = self::GEMINI_URL_GENERATE_CONTENT;
				break;
			case 'mistral':
				$endpoints['chat_completions'] = self::MISTRAL_URL_CHAT_COMPLETIONS;
				break;
			case 'claude':
				$endpoints['messages'] = self::CLAUDE_URL_MESSAGES;
				break;
			default:
				// Default to OpenAI for backward compatibility
				$endpoints['usage']            = self::OPENAI_URL_USAGE;
				$endpoints['chat_completions'] = self::OPENAI_URL_CHAT_COMPLETIONS;
				break;
		}

		return $endpoints;
	}

	private function getProviderName( $provider ) {
		// Sanitize provider parameter
		$provider = sanitize_text_field( strtolower( $provider ) );

		switch ( $provider ) {
			case 'openai':
				return 'OpenAI';
			case 'deepseek':
				return 'DeepSeek';
			case 'gemini':
				return 'Gemini';
			case 'mistral':
				return 'Mistral';
			case 'claude':
				return 'Claude';
			default:
				return ucfirst( $provider );
		}
	}

	/**
	 * Check if the provider uses chat completions format (OpenAI) or completions format (DeepSeek)
	 *
	 * @param string $provider The AI provider (openai, deepseek, gemini, etc.)
	 * @return bool True if using chat completions format, false if using completions format
	 */
	private function isChatCompletionsProvider( $provider ) {
		$provider = sanitize_text_field( strtolower( $provider ) );

		switch ( $provider ) {
			case 'openai':
				return true;
			case 'deepseek':
				return false;
			case 'gemini':
				return false; // Gemini uses its own generateContent format
			case 'mistral':
				return true; // Mistral uses OpenAI-compatible chat completions format
			case 'claude':
				return false; // Claude uses its own messages format
			default:
				return true; // Default to chat completions for backward compatibility
		}
	}

	/**
	 * Check if the provider is Claude (uses Anthropic API format)
	 *
	 * @param string $provider The AI provider (openai, deepseek, claude, etc.)
	 * @return bool True if provider is Claude
	 */
	private function isClaudeProvider( $provider ) {
		return sanitize_text_field( strtolower( $provider ) ) === 'claude';
	}

	/**
	 * Check if the provider is Gemini (uses unique API format)
	 *
	 * @param string $provider The AI provider (openai, deepseek, gemini, etc.)
	 * @return bool True if provider is Gemini
	 */
	private function isGeminiProvider( $provider ) {
		return sanitize_text_field( strtolower( $provider ) ) === 'gemini';
	}

	public function getLicenseKey( $provider ) {
		$options = get_option( 'seopress_pro_option_name' );

		$api_key = '';

		// Check for provider-specific constants first
		$constant_name = 'SEOPRESS_' . strtoupper( $provider ) . '_KEY';
		if ( defined( $constant_name ) && ! empty( constant( $constant_name ) ) && is_string( constant( $constant_name ) ) ) {
			$api_key = constant( $constant_name );
		} else {
			$api_key = isset( $options[ 'seopress_ai_' . $provider . '_api_key' ] ) ? $options[ 'seopress_ai_' . $provider . '_api_key' ] : '';
		}

		return $api_key;
	}

	public function checkLicenseKeyExists( $provider ) {
		$api_key       = $this->getLicenseKey( $provider );
		$provider_name = $this->getProviderName( $provider );

		// Check for empty keys
		if ( empty( $api_key ) ) {
			$data = array(
				'code'    => 'error',
				'message' => sprintf(
					/* translators: %s: provider name */
					__( 'Your %s API key has not been entered. Please enter your API key.', 'wp-seopress-pro' ),
					$provider_name
				),
			);

			return $data;
		}

		// Check for common placeholder values
		$placeholder_values = array(
			'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
			'xxxxxxxx',
			'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
			'sk-xxxxxxxx',
		);

		if ( in_array( $api_key, $placeholder_values ) ) {
			$data = array(
				'code'    => 'error',
				'message' => sprintf(
					/* translators: %s: provider name */
					__( 'Your %1$s API key appears to be a placeholder. Please enter your actual API key from %2$s website.', 'wp-seopress-pro' ),
					$provider_name,
					$provider_name
				),
			);

			return $data;
		}

		$endpoints = $this->getProviderEndpoints( $provider );

		// Use different endpoints and auth methods for different providers
		if ( strtolower( $provider ) === 'openai' ) {
			$url    = $endpoints['usage'];
			$params = array(
				'date' => gmdate( 'Y-m-d' ),
			);
			$url      = add_query_arg( $params, $url );
			$response = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
					),
					'timeout' => 30,
				)
			);
		} elseif ( strtolower( $provider ) === 'mistral' ) {
			// Mistral: Use a simple chat completions request to validate the key.
			$url      = $endpoints['chat_completions'];
			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => $this->getDefaultModel( $provider ),
							'messages'   => array(
								array(
									'role'    => 'user',
									'content' => 'Hello',
								),
							),
							'max_tokens' => 10,
						)
					),
					'timeout' => 30,
				)
			);
		} elseif ( $this->isGeminiProvider( $provider ) ) {
			// Gemini: Use a simple generateContent request to validate the key.
			$model = $this->getDefaultModel( $provider );
			$url   = $endpoints['generate_content'] . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'contents' => array(
								array(
									'role'  => 'user',
									'parts' => array(
										array( 'text' => 'Hello' ),
									),
								),
							),
							'generationConfig' => array(
								'maxOutputTokens' => 10,
							),
						)
					),
					'timeout' => 30,
				)
			);

			// For Gemini, provide more detailed error info.
			if ( ! is_wp_error( $response ) ) {
				$httpCode = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $httpCode ) {
					$body          = wp_remote_retrieve_body( $response );
					$body_decoded  = json_decode( $body, true );
					$error_message = isset( $body_decoded['error']['message'] ) ? $body_decoded['error']['message'] : '';

					// 429 means rate limited - the key IS valid, just quota exceeded.
					if ( 429 === $httpCode ) {
						return array(
							'code'    => 'success',
							'message' => sprintf(
								/* translators: %s: provider name */
								__( 'Your %s API key is valid but you have exceeded your quota. Please check your billing settings at Google AI Studio or wait for your quota to reset.', 'wp-seopress-pro' ),
								$provider_name
							),
						);
					}

					return array(
						'code'    => 'error',
						'message' => sprintf(
							/* translators: %1$s: provider name, %2$s: error code, %3$s: error details */
							__( 'Your %1$s API key is invalid or has expired. Error %2$s: %3$s', 'wp-seopress-pro' ),
							$provider_name,
							esc_html( $httpCode ),
							esc_html( $error_message )
						),
					);
				}
			}
		} elseif ( $this->isClaudeProvider( $provider ) ) {
			// Claude: Use a simple messages request to validate the key.
			$url      = $endpoints['messages'];
			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => $this->getDefaultModel( $provider ),
							'max_tokens' => 10,
							'messages'   => array(
								array(
									'role'    => 'user',
									'content' => 'Hello',
								),
							),
						)
					),
					'timeout' => 30,
				)
			);
		} else {
			// For DeepSeek and other providers, use balance endpoint
			$url      = isset( $endpoints['balance'] ) ? $endpoints['balance'] : $endpoints['usage'];
			$response = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
					),
					'timeout' => 30,
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'code'    => 'error',
				'message' => sprintf(
					/* translators: %1$s: provider name, %2$s: error message */
					__( 'Failed to connect to %1$s API: %2$s', 'wp-seopress-pro' ),
					$provider_name,
					$response->get_error_message()
				),
			);
		}

		$httpCode = wp_remote_retrieve_response_code( $response );

		if ( $httpCode === 200 ) {
			return array(
				'code'    => 'success',
				'message' => sprintf(
					/* translators: %s: provider name */
					__( 'Your %s API key is valid.', 'wp-seopress-pro' ),
					$provider_name
				),
			);
		} else {
			return array(
				'code'    => 'error',
				'message' => sprintf(
					/* translators: %1$s: provider name, %2$s: error code */
					__( 'Your %1$s API key is invalid or has expired. Error: %2$s', 'wp-seopress-pro' ),
					$provider_name,
					esc_html( $httpCode )
				),
			);
		}
	}

	public function checkLicenseKeyExpiration( $provider ) {
		$api_key       = $this->getLicenseKey( $provider );
		$provider_name = $this->getProviderName( $provider );

		// Resolve the model through Completions::getAIModel() so the
		// stale-value guard kicks in here too. Without this, a customer with
		// an obsolete model in their DB (e.g. "gpt-5-chat-latest") would still
		// see the test connection fail even after the server-side guard
		// protects normal generation requests.
		$completions_service = seopress_pro_get_service( 'Completions' );
		if ( null !== $completions_service ) {
			$model_name = $completions_service->getAIModel( $provider );
		} else {
			$options    = get_option( 'seopress_pro_option_name' );
			$model_name = isset( $options[ 'seopress_ai_' . $provider . '_model' ] ) ? $options[ 'seopress_ai_' . $provider . '_model' ] : $this->getDefaultModel( $provider );
		}

		$endpoints = $this->getProviderEndpoints( $provider );

		// Handle Gemini differently - it uses query param auth and different format
		if ( $this->isGeminiProvider( $provider ) ) {
			$url  = $endpoints['generate_content'] . $model_name . ':generateContent?key=' . $api_key;
			$body = array(
				'contents'         => array(
					array(
						'parts' => array(
							array( 'text' => 'test' ),
						),
					),
				),
				'generationConfig' => array(
					'maxOutputTokens' => 5,
				),
			);
			$args = array(
				'body'        => wp_json_encode( $body ),
				'timeout'     => '30',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
			);
		} elseif ( $this->isClaudeProvider( $provider ) ) {
			// Claude uses x-api-key header and different format
			$url  = $endpoints['messages'];
			$body = array(
				'model'      => $model_name,
				'max_tokens' => 10,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'test',
					),
				),
			);
			$args = array(
				'body'        => wp_json_encode( $body ),
				'timeout'     => '30',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
			);
		} else {
			// Check if this is a GPT-5 model
			$is_gpt5_model = strpos( $model_name, 'gpt-5' ) === 0;

			// Use the appropriate endpoint based on provider and model
			if ( $is_gpt5_model ) {
				// GPT-5 uses Responses API
				$url = $endpoints['responses'];
			} elseif ( $this->isChatCompletionsProvider( $provider ) ) {
				$url = $endpoints['chat_completions'];
			} else {
				$url = $endpoints['completions'];
			}

			// Build request body based on provider format
			if ( $is_gpt5_model ) {
				// GPT-5 uses Responses API with different parameters
				// Need more tokens as reasoning uses many tokens
				$body = array(
					'model'             => $model_name,
					'input'             => 'test',
					'max_output_tokens' => 500,
					'reasoning'         => array(
						'effort' => 'medium',
					),
				);
			} elseif ( $this->isChatCompletionsProvider( $provider ) ) {
				// OpenAI Chat Completions format
				$body = array(
					'model'       => $model_name,
					'temperature' => 1,
					'max_tokens'  => 10,
					'messages'    => array(
						array(
							'role'    => 'user',
							'content' => 'test',
						),
					),
				);
			} else {
				// DeepSeek completions format
				$body = array(
					'model'       => $model_name,
					'temperature' => 1,
					'max_tokens'  => 10,
					'prompt'      => 'test',
				);
			}

			$args = array(
				'body'        => wp_json_encode( $body ),
				'timeout'     => '30',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
			);
		}

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'code'    => 'error',
				'message' => sprintf(
					/* translators: %1$s: provider name, %2$s: error message */
					__( 'Failed to connect to %1$s API: %2$s', 'wp-seopress-pro' ),
					$provider_name,
					$response->get_error_message()
				),
			);
		}

		$httpCode = wp_remote_retrieve_response_code( $response );

		// Get response body for detailed error analysis
		$response_body = wp_remote_retrieve_body( $response );
		$error_data    = json_decode( $response_body, true );

		if ( $httpCode === 200 ) {
			return array(
				'code'    => 'success',
				'message' => sprintf(
					/* translators: %1$s: provider name, %2$s: model name */
					__( 'Your %1$s API key is valid and the model %2$s is available.', 'wp-seopress-pro' ),
					$provider_name,
					'<strong>' . esc_html( $model_name ) . '</strong>'
				),
			);
		} else {
			$error_message = '';

			// Check if the error is related to model access
			if ( isset( $error_data['error']['message'] ) ) {
				$error_message = $error_data['error']['message'];
			}

			// Check for common model access issues
			if ( strpos( $error_message, 'model' ) !== false || strpos( $error_message, 'does not exist' ) !== false || $httpCode === 404 ) {
				return array(
					'code'    => 'error',
					'message' => sprintf(
						/* translators: %1$s: provider name, %2$s: model name, %3$s: error code */
						__( 'Your %1$s API key is valid, but the model %2$s is not available for your account. Error: %3$s. Please select a different model or upgrade your account to access this model.', 'wp-seopress-pro' ),
						$provider_name,
						'<strong>' . esc_html( $model_name ) . '</strong>',
						esc_html( $httpCode )
					),
				);
			}

			return array(
				'code'    => 'error',
				'message' => sprintf(
					/* translators: %1$s: provider name, %2$s: error code, %3$s: usage url, %4$s: provider name */
					__( 'Your %1$s API key is invalid or has expired. Error: %2$s. Go to your <a href="%3$s" target="_blank">%4$s Usage page</a> to check this.', 'wp-seopress-pro' ),
					$provider_name,
					esc_html( $httpCode ),
					$this->getProviderUsageUrl( $provider ),
					$provider_name
				),
			);
		}
	}

	/**
	 * Get the usage/balance URL for the provider
	 *
	 * @param string $provider The AI provider
	 * @return string The usage URL
	 */
	private function getProviderUsageUrl( $provider ) {
		$provider = sanitize_text_field( strtolower( $provider ) );

		switch ( $provider ) {
			case 'openai':
				return 'https://platform.openai.com/usage';
			case 'deepseek':
				return 'https://platform.deepseek.com/usage';
			case 'gemini':
				return 'https://aistudio.google.com/apikey';
			case 'mistral':
				return 'https://console.mistral.ai/usage';
			case 'claude':
				return 'https://platform.claude.com/settings/billing';
			default:
				return '#';
		}
	}

	private function getDefaultModel( $provider ) {
		$provider = sanitize_text_field( strtolower( $provider ) );

		switch ( $provider ) {
			case 'openai':
				return 'gpt-5.2-chat-latest';
			case 'deepseek':
				return 'deepseek-chat';
			case 'gemini':
				return 'gemini-2.5-flash';
			case 'mistral':
				return 'mistral-small-latest';
			case 'claude':
				return 'claude-sonnet-4-20250514';
			default:
				return 'gpt-5.2-chat-latest';
		}
	}
}

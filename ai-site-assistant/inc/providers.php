<?php
/**
 * AI providers. Chat goes through the OpenAI-compatible /chat/completions API that OpenAI, Gemini,
 * Claude, DeepSeek, Groq, OpenRouter, Ollama (/v1) and most other hosts expose. Embeddings use either
 * Ollama's /api/embed or the OpenAI-compatible /embeddings API.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provider presets.
 *
 * Each preset: label, base (default base URL), path (appended to the base), model (default chat model),
 * key (an API key is required), usage (accepts stream_options.include_usage), embed (default model for
 * OpenAI-compatible /embeddings on the same base URL, '' = the provider has no embeddings API).
 *
 * @return array<string,array{label:string,base:string,path:string,model:string,key:bool,usage:bool,embed:string}>
 */
function aisa_providers() {
	$list = array(
		'openai'            => array(
			'label' => 'OpenAI',
			'base'  => 'https://api.openai.com/v1',
			'path'  => '/chat/completions',
			'model' => 'gpt-6-luna',
			'key'   => true,
			'usage' => true,
			'embed' => 'text-embedding-3-small',
		),
		'gemini'            => array(
			'label' => 'Google Gemini',
			'base'  => 'https://generativelanguage.googleapis.com/v1beta/openai',
			'path'  => '/chat/completions',
			'model' => 'gemini-3.8-flash',
			'key'   => true,
			'usage' => false,
			'embed' => 'gemini-embedding-001',
		),
		'anthropic'         => array(
			'label' => 'Anthropic Claude',
			'base'  => 'https://api.anthropic.com/v1',
			'path'  => '/chat/completions',
			'model' => 'claude-haiku-5-5',
			'key'   => true,
			'usage' => true,
			'embed' => '',
		),
		'deepseek'          => array(
			'label' => 'DeepSeek',
			'base'  => 'https://api.deepseek.com',
			'path'  => '/chat/completions',
			'model' => 'deepseek-chat',
			'key'   => true,
			'usage' => true,
			'embed' => '',
		),
		'groq'              => array(
			'label' => 'Groq',
			'base'  => 'https://api.groq.com/openai/v1',
			'path'  => '/chat/completions',
			'model' => '',
			'key'   => true,
			'usage' => false,
			'embed' => '',
		),
		'openrouter'        => array(
			'label' => 'OpenRouter',
			'base'  => 'https://openrouter.ai/api/v1',
			'path'  => '/chat/completions',
			'model' => 'openrouter/auto',
			'key'   => true,
			'usage' => false,
			'embed' => 'openai/text-embedding-3-small',
		),
		'ollama'            => array(
			'label' => __( 'Ollama (self-hosted)', 'ai-site-assistant' ),
			'base'  => 'http://localhost:11434',
			'path'  => '/v1/chat/completions',
			'model' => 'llama3.1:8b',
			'key'   => false,
			'usage' => false,
			'embed' => '',
		),
		'openai_compatible' => array(
			'label' => __( 'Other OpenAI-compatible API (base URL ends in /v1)', 'ai-site-assistant' ),
			'base'  => '',
			'path'  => '/chat/completions',
			'model' => '',
			'key'   => false,
			'usage' => false,
			'embed' => '',
		),
	);
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$list['mock'] = array(
			'label' => __( 'Mock (testing: no AI is called)', 'ai-site-assistant' ),
			'base'  => '',
			'path'  => '',
			'model' => 'mock',
			'key'   => false,
			'usage' => false,
			'embed' => '',
		);
	}
	return $list;
}

/**
 * Provider labels for the settings select.
 *
 * @return array<string,string>
 */
function aisa_provider_labels() {
	return wp_list_pluck( aisa_providers(), 'label' );
}

/**
 * Resolved chat configuration.
 *
 * @return array{provider:string,url:string,key:string,model:string,base:string,preset:array}
 */
function aisa_chat_config() {
	$providers = aisa_providers();
	$provider  = (string) aisa_setting( 'provider' );
	if ( ! isset( $providers[ $provider ] ) ) {
		$provider = 'openai';
	}
	$p     = $providers[ $provider ];
	$base  = aisa_setting( 'base_url' ) ? aisa_setting( 'base_url' ) : $p['base'];
	$model = aisa_setting( 'model' ) ? aisa_setting( 'model' ) : $p['model'];
	return array(
		'provider' => $provider,
		'base'     => untrailingslashit( $base ),
		'url'      => $base ? untrailingslashit( $base ) . $p['path'] : '',
		'key'      => (string) aisa_setting( 'api_key' ),
		'model'    => (string) $model,
		'preset'   => $p,
	);
}

/**
 * Request headers for a provider call.
 *
 * @param string $key Bearer token.
 * @return array<string,string>
 */
function aisa_headers( $key ) {
	$h = array(
		'Content-Type' => 'application/json',
		'Accept'       => 'application/json',
	);
	if ( '' !== $key ) {
		$h['Authorization'] = 'Bearer ' . $key;
	}
	return $h;
}

/**
 * Error message from a provider error body.
 *
 * @param int    $code HTTP status.
 * @param string $body Response body.
 * @return WP_Error
 */
function aisa_provider_error( $code, $body ) {
	$json = json_decode( (string) $body, true );
	$msg  = '';
	if ( is_array( $json ) ) {
		if ( isset( $json['error']['message'] ) ) {
			$msg = $json['error']['message'];
		} elseif ( isset( $json['error'] ) && is_string( $json['error'] ) ) {
			$msg = $json['error'];
		} elseif ( isset( $json['message'] ) ) {
			$msg = $json['message'];
		}
	}
	if ( '' === $msg ) {
		$msg = wp_strip_all_tags( substr( (string) $body, 0, 200 ) );
	}
	$hint = array(
		401 => __( 'Check the API key / bearer token.', 'ai-site-assistant' ),
		402 => __( 'The provider account has run out of balance.', 'ai-site-assistant' ),
		404 => __( 'Check the base URL and the model name.', 'ai-site-assistant' ),
		429 => __( 'The provider is rate-limiting requests.', 'ai-site-assistant' ),
	);
	$msg = sprintf( 'HTTP %d: %s', $code, $msg ? $msg : __( 'no details', 'ai-site-assistant' ) ) . ( isset( $hint[ $code ] ) ? ' ' . $hint[ $code ] : '' );
	return new WP_Error(
		'aisa_provider',
		$msg,
		array(
			'status' => 502,
			'http'   => $code,
		)
	);
}

/**
 * Adjust a request body after the provider rejected one of its parameters, so the request can be retried.
 * Newer models reject a custom temperature (Claude 4.7+, OpenAI reasoning models) or want
 * max_completion_tokens instead of max_tokens.
 *
 * @param array    $body Request body (changed in place).
 * @param WP_Error $err  The error from the provider.
 * @return bool True when the body changed and the request is worth retrying.
 */
function aisa_relax_request( &$body, $err ) {
	$data = $err->get_error_data();
	if ( ! is_array( $data ) || ! isset( $data['http'] ) || ! in_array( (int) $data['http'], array( 400, 422 ), true ) ) {
		return false;
	}
	$msg = strtolower( $err->get_error_message() );
	if ( isset( $body['max_tokens'] ) && false !== strpos( $msg, 'max_tokens' ) ) {
		$body['max_completion_tokens'] = $body['max_tokens'];
		unset( $body['max_tokens'] );
		return true;
	}
	if ( isset( $body['temperature'] ) && false !== strpos( $msg, 'temperature' ) ) {
		unset( $body['temperature'] );
		return true;
	}
	if ( isset( $body['stream_options'] ) && false !== strpos( $msg, 'stream_options' ) ) {
		unset( $body['stream_options'] );
		return true;
	}
	return false;
}

/**
 * Send a chat completion.
 *
 * @param array         $messages OpenAI-style messages.
 * @param callable|null $on_delta Called with each text fragment when streaming. Null = no streaming.
 * @param array         $args     Overrides: max_tokens, temperature, mock_answer.
 * @return array{text:string,tokens:int,model:string}|WP_Error
 */
function aisa_chat( $messages, $on_delta = null, $args = array() ) {
	$cfg = aisa_chat_config();

	if ( 'mock' === $cfg['provider'] ) {
		$text = isset( $args['mock_answer'] ) ? $args['mock_answer'] : 'Mock reply: the AI provider is set to Mock.';
		if ( $on_delta ) {
			foreach ( preg_split( '/(?<=\s)/u', $text ) as $piece ) {
				call_user_func( $on_delta, $piece );
			}
		}
		return array(
			'text'   => $text,
			'tokens' => 0,
			'model'  => 'mock',
		);
	}
	if ( '' === $cfg['url'] ) {
		return new WP_Error( 'aisa_config', __( 'Set the API base URL in AI Assistant → Settings.', 'ai-site-assistant' ), array( 'status' => 500 ) );
	}
	if ( '' === $cfg['model'] ) {
		return new WP_Error( 'aisa_config', __( 'Set the chat model in AI Assistant → Settings → AI model.', 'ai-site-assistant' ), array( 'status' => 500 ) );
	}

	$body = array(
		'model'       => $cfg['model'],
		'messages'    => $messages,
		'temperature' => isset( $args['temperature'] ) ? (float) $args['temperature'] : (float) aisa_setting( 'temperature' ),
		'max_tokens'  => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : (int) aisa_setting( 'max_tokens' ),
		'stream'      => (bool) $on_delta,
	);
	if ( $on_delta && $cfg['preset']['usage'] ) {
		$body['stream_options'] = array( 'include_usage' => true );
	}

	// A rejected parameter fails before any text arrives, so a retry never repeats streamed output.
	for ( $try = 0; $try < 3; $try++ ) {
		$res = aisa_chat_request( $cfg, $body, $on_delta );
		if ( ! is_wp_error( $res ) || ! aisa_relax_request( $body, $res ) ) {
			return $res;
		}
	}
	return $res;
}

/**
 * One chat completion request (streaming when a callback is given and cURL is available).
 *
 * @param array         $cfg      aisa_chat_config().
 * @param array         $body     Request body.
 * @param callable|null $on_delta Fragment callback.
 * @return array{text:string,tokens:int,model:string}|WP_Error
 */
function aisa_chat_request( $cfg, $body, $on_delta ) {
	if ( $on_delta && function_exists( 'curl_init' ) ) {
		return aisa_chat_stream( $cfg, $body, $on_delta );
	}

	$body['stream'] = false;
	unset( $body['stream_options'] );
	$res = wp_remote_post(
		$cfg['url'],
		array(
			'headers' => aisa_headers( $cfg['key'] ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 90,
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'aisa_provider', $res->get_error_message(), array( 'status' => 502 ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = wp_remote_retrieve_body( $res );
	if ( 200 !== $code ) {
		return aisa_provider_error( $code, $raw );
	}
	$json = json_decode( $raw, true );
	$text = isset( $json['choices'][0]['message']['content'] ) ? (string) $json['choices'][0]['message']['content'] : '';
	if ( $on_delta && '' !== $text ) {
		call_user_func( $on_delta, $text );
	}
	return array(
		'text'   => $text,
		'tokens' => isset( $json['usage']['total_tokens'] ) ? (int) $json['usage']['total_tokens'] : 0,
		'model'  => isset( $json['model'] ) ? (string) $json['model'] : $cfg['model'],
	);
}

/**
 * Streaming chat completion over cURL (the WordPress HTTP API buffers whole responses).
 *
 * @param array    $cfg      aisa_chat_config().
 * @param array    $body     Request body.
 * @param callable $on_delta Fragment callback.
 * @return array{text:string,tokens:int,model:string}|WP_Error
 */
function aisa_chat_stream( $cfg, $body, $on_delta ) {
	$state = array(
		'buf'    => '',
		'raw'    => '',
		'text'   => '',
		'tokens' => 0,
		'model'  => $cfg['model'],
	);
	$headers = array();
	foreach ( aisa_headers( $cfg['key'] ) as $k => $v ) {
		$headers[] = $k . ': ' . $v;
	}
	$headers[] = 'Accept: text/event-stream';

	$ch = curl_init( $cfg['url'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
	// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => wp_json_encode( $body ),
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_CAINFO         => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
			CURLOPT_WRITEFUNCTION  => function ( $ch, $data ) use ( &$state, $on_delta ) {
				$state['buf'] .= $data;
				while ( false !== ( $pos = strpos( $state['buf'], "\n" ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
					$line         = trim( substr( $state['buf'], 0, $pos ) );
					$state['buf'] = substr( $state['buf'], $pos + 1 );
					if ( 0 !== strpos( $line, 'data:' ) ) {
						$state['raw'] .= $line;
						continue;
					}
					$payload = trim( substr( $line, 5 ) );
					if ( '[DONE]' === $payload ) {
						continue;
					}
					$json = json_decode( $payload, true );
					if ( isset( $json['model'] ) ) {
						$state['model'] = (string) $json['model'];
					}
					if ( isset( $json['usage']['total_tokens'] ) ) {
						$state['tokens'] = (int) $json['usage']['total_tokens'];
					}
					$piece = isset( $json['choices'][0]['delta']['content'] ) ? (string) $json['choices'][0]['delta']['content'] : '';
					if ( '' !== $piece ) {
						$state['text'] .= $piece;
						call_user_func( $on_delta, $piece );
					}
				}
				return strlen( $data );
			},
		)
	);
	// phpcs:enable
	$ok   = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
	$err  = curl_error( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
	curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close

	if ( false === $ok && '' === $state['text'] ) {
		return new WP_Error( 'aisa_provider', __( 'Could not reach the AI provider:', 'ai-site-assistant' ) . ' ' . $err, array( 'status' => 502 ) );
	}
	if ( 200 !== $code ) {
		return aisa_provider_error( $code, $state['raw'] . $state['buf'] );
	}
	return array(
		'text'   => $state['text'],
		'tokens' => $state['tokens'],
		'model'  => $state['model'],
	);
}

/**
 * Embeddings API types for the settings select.
 *
 * @return array<string,string>
 */
function aisa_embed_types() {
	$list = array(
		'ollama' => __( 'Ollama (/api/embed)', 'ai-site-assistant' ),
		'openai' => __( 'OpenAI-compatible (/embeddings): OpenAI, Gemini, OpenRouter, Mistral…', 'ai-site-assistant' ),
	);
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$list['mock'] = __( 'Mock (testing: fake vectors)', 'ai-site-assistant' );
	}
	return $list;
}

/**
 * Embeddings configuration, or null when semantic search is off or not set up.
 *
 * With no embeddings URL, the chat provider's URL and key are reused when it offers the same API:
 * Ollama for Ollama embeddings, or an OpenAI-style provider for /embeddings.
 *
 * @return array{type:string,url:string,key:string,model:string}|null
 */
function aisa_embed_config() {
	if ( ! aisa_setting( 'embed_enabled' ) ) {
		return null;
	}
	$type = (string) aisa_setting( 'embed_provider' );
	if ( ! isset( aisa_embed_types()[ $type ] ) ) {
		$type = 'ollama';
	}
	$model = (string) aisa_setting( 'embed_model' );
	if ( 'mock' === $type ) {
		return array(
			'type'  => 'mock',
			'url'   => '',
			'key'   => '',
			'model' => 'mock-embed',
		);
	}

	$chat  = aisa_chat_config();
	$base  = (string) aisa_setting( 'embed_url' );
	$key   = (string) aisa_setting( 'embed_key' );
	$ollama = 'ollama' === $type && 'ollama' === $chat['provider'];
	$reuse  = '' === $base && ( $ollama || ( 'openai' === $type && ! in_array( $chat['provider'], array( 'ollama', 'deepseek', 'mock' ), true ) ) );
	if ( $reuse ) {
		$base = $chat['base'];
	}
	if ( '' === $key && ( $reuse || $ollama ) ) {
		$key = $chat['key']; // Ollama on its own address keeps sharing the chat token, as in 1.0.
	}
	if ( '' === $model ) {
		$model = 'ollama' === $type ? 'nomic-embed-text' : ( $reuse ? $chat['preset']['embed'] : '' );
	}
	if ( '' === $base || '' === $model ) {
		return null;
	}
	return array(
		'type'  => $type,
		'url'   => untrailingslashit( $base ) . ( 'ollama' === $type ? '/api/embed' : '/embeddings' ),
		'key'   => $key,
		'model' => $model,
	);
}

/**
 * Deterministic fake vectors for the mock embeddings type: hashed word counts, so texts that share
 * words come out similar and the semantic ranking can be tested without an AI service.
 *
 * @param string $text Text.
 * @return float[]
 */
function aisa_mock_vector( $text ) {
	$vec = array_fill( 0, 64, 0.0 );
	foreach ( preg_split( '/\W+/u', mb_strtolower( (string) $text ), -1, PREG_SPLIT_NO_EMPTY ) as $w ) {
		$vec[ crc32( $w ) % 64 ] += 1.0;
	}
	return $vec;
}

/**
 * Embed texts.
 *
 * @param string[] $texts Texts.
 * @return float[][]|WP_Error One vector per text.
 */
function aisa_embed( $texts ) {
	$cfg = aisa_embed_config();
	if ( ! $cfg ) {
		return new WP_Error( 'aisa_embed', __( 'Semantic search is off or has no embeddings URL and model.', 'ai-site-assistant' ) );
	}
	if ( 'mock' === $cfg['type'] ) {
		return array_map( 'aisa_mock_vector', array_values( $texts ) );
	}
	$out = array();
	foreach ( array_chunk( array_values( $texts ), 16 ) as $batch ) {
		$res = wp_remote_post(
			$cfg['url'],
			array(
				'headers' => aisa_headers( $cfg['key'] ),
				'body'    => wp_json_encode(
					array(
						'model' => $cfg['model'],
						'input' => $batch,
					)
				),
				'timeout' => 60,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return aisa_provider_error( $code, wp_remote_retrieve_body( $res ) );
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		$vecs = array();
		if ( 'ollama' === $cfg['type'] ) {
			$vecs = isset( $json['embeddings'] ) && is_array( $json['embeddings'] ) ? $json['embeddings'] : array();
		} elseif ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			$data = $json['data'];
			usort( $data, fn( $a, $b ) => ( isset( $a['index'] ) ? (int) $a['index'] : 0 ) <=> ( isset( $b['index'] ) ? (int) $b['index'] : 0 ) );
			$vecs = array_values( array_filter( array_map( fn( $d ) => isset( $d['embedding'] ) ? $d['embedding'] : null, $data ), 'is_array' ) );
		}
		if ( count( $vecs ) !== count( $batch ) ) {
			return new WP_Error(
				'aisa_embed',
				'ollama' === $cfg['type']
					? __( 'The embeddings response was empty. Is the model pulled on the Ollama server?', 'ai-site-assistant' )
					: __( 'The embeddings response was empty. Check the embeddings model name.', 'ai-site-assistant' )
			);
		}
		foreach ( $vecs as $vec ) {
			$out[] = array_map( 'floatval', $vec );
		}
	}
	return $out;
}

/**
 * Pack a vector for storage (normalised, float32, base64).
 *
 * @param float[] $vec Vector.
 * @return string
 */
function aisa_pack_vector( $vec ) {
	$norm = sqrt( array_sum( array_map( fn( $x ) => $x * $x, $vec ) ) );
	if ( $norm > 0 ) {
		$vec = array_map( fn( $x ) => $x / $norm, $vec );
	}
	return base64_encode( pack( 'g*', ...$vec ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Unpack a stored vector.
 *
 * @param string $packed Packed vector.
 * @return float[]
 */
function aisa_unpack_vector( $packed ) {
	$bin = base64_decode( (string) $packed, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	return $bin ? array_values( unpack( 'g*', $bin ) ) : array();
}

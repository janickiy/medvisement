<?php

class Lemmatize {

	private $curl;
	private $api_url;

	public function __construct() {
		$this->curl = curl_init();

		if ( false === getenv('SERVER_NAME') ) {
			$this->api_url = 'http://localhost:5000/lemmatize';
		}
		else {
			$this->api_url = 'http://python:5000/lemmatize';
		}
	}

	public function lemmatize_text( $text ) {
		curl_setopt_array( $this->curl, array(
			CURLOPT_URL            => $this->api_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( [ 'text' => $text], JSON_UNESCAPED_UNICODE ),
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json'
			),
		) );

		return curl_exec( $this->curl );
	}

	public function close_connection() {
		curl_close( $this->curl );
	}

}
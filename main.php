<?php

require_once __DIR__ . "/vendor/apitracking/Init.class.php";
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . "/email.php";

define("API_KEY", "ca1b2aaf-5b05-4aee-aec0-cce3ac19a233");
define("CARRIER_CODE", "brazil-correios");
define("EMAIL_SUBJECT", "Status da Encomenda");
define("EMAIL_RECIPIENT", "joao.macedo@elastic.fit");



$tracking_codes = '[ "OA016913717BR" ]';
$tracking_codes = json_decode($tracking_codes);

$trackings = new AfterShip\Trackings(API_KEY);

foreach ($tracking_codes as $tracking_code) {
	try {
		$tracking_info = [
			'slug'    => CARRIER_CODE,
		];
		$response = $trackings->create($tracking_code, $tracking_info);
		$response = $trackings->get(CARRIER_CODE, $tracking_code, array("lang" => "pt"));
	} catch(Exception $e) {
		$response = $trackings->get(CARRIER_CODE, $tracking_code, array("lang" => "pt"));
	}

	// DEBUG: use serial in case api is max out
	/*$response_file = fopen("./response.serial", "r");
	$response = fread($response_file, filesize("./response.serial"));
	fclose($response_file);
	$response = unserialize($response);*/


	switch ($response['meta']['code']) {
		case 429:
			echo "Too many requests";
			break;
		case 200:
			$html_message_progress = format_html_template($tracking_code, $response['data']['tracking'], true);
			$html_message = format_html_template($tracking_code, $response['data']['tracking']); // without progress bar for the pdf


			$status = $response['data']['tracking']['tag'];
			if (isset($status) && key_exists($status, STATUS_ARRAY)) {
				$pt_status = STATUS_ARRAY[$status];
				$subject_status = " - " . $pt_status;
			}

			$pdf_str = html_to_pdf($html_message);
			send_email(EMAIL_SUBJECT . $subject_status, EMAIL_RECIPIENT, $html_message_progress, "unable to dislay the html content", $pdf_str);
			break;
		default:
			echo "Unidentified code: " . $response['meta']['code'];
			break;
	}
}

?>

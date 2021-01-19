<?php

define("TRACKING_INFO_TEMPLATE", __DIR__ . "/html-templates/tracking-info.html");
define("TABLE_ITEM_TEMPLATE", __DIR__ . "/html-templates/table-item.html");
define("PDF_TEMP_FILE", __DIR__ . "/temp/status.pdf");
define("EMAIL_HOST", "smtp-mail.outlook.com");
define("EMAIL_FROM", "user@test.com");
define("EMAIL_USER", "user@test.com");
define("EMAIL_PASSWORD", "*********");
define("EMAIL_NAME", "User");
define("STATUS_CSS_FILE", __DIR__ . "/html-templates/progress.css");
define("STATUS_HTML_FILE", __DIR__ . "/html-templates/progress-%s.html");
const STATUS_ARRAY = array("In Transit" => "Encaminhado", "Delivered" => "Entregue", "Exception" => "Erro"); // Não tem o status de "Postado" nesta API
const STATUS_ARRAY_FILE_NUMBER = array("In Transit" => "2", "Delivered" => "3", "Exception" => "0");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;
use Spipu\Html2Pdf\Exception\Html2PdfException;

require __DIR__ . '/vendor/autoload.php';



function format_item_info($item_info) {
	$item_template_file = fopen(TABLE_ITEM_TEMPLATE, "r") or die(sprintf("Couldn't find the file '%s'", TABLE_ITEM_TEMPLATE));
	$item_template = fread($item_template_file, filesize(TABLE_ITEM_TEMPLATE));
	fclose($item_template_file);

	$item = "";

	foreach ($item_info as $track_info) {
		$time = strtotime($track_info['checkpoint_time']);
		$date = date("d/m/Y H:i", $time);
		$date = str_replace(" ", "<br>", $date);
		$item .= sprintf($item_template, $date, $track_info['location'], $track_info['message']);
	}

	return $item;
}



function format_html_template($tracking_code, $item, $progress = false) {
	$html_template_file = fopen(TRACKING_INFO_TEMPLATE, "r") or die(sprintf("Couldn't find the file '%s'", TRACKING_INFO_TEMPLATE));
	$html_template = fread($html_template_file, filesize(TRACKING_INFO_TEMPLATE));
	fclose($html_template_file);

	/////////////////////// Add the status bar //////////////////////
	$status_css = "";
	$status_html = "";

	if ($progress) {
		$status = $item['tag'];

		if (isset($status) && key_exists($status, STATUS_ARRAY_FILE_NUMBER)) {
			$status_css_file = fopen(STATUS_CSS_FILE, "r") or die(sprintf("Couldn't find the file '%s'", STATUS_CSS_FILE));
			$status_css = fread($status_css_file, filesize(STATUS_CSS_FILE));
			$status_css = "<style>" . $status_css . "</style>";
			fclose($status_css_file);

			$file_name = sprintf(STATUS_HTML_FILE,  STATUS_ARRAY_FILE_NUMBER[$status]);
			$status_html_file = fopen($file_name, "r") or die(sprintf("Couldn't find the file '%s'", $file_name));
			$status_html = fread($status_html_file, filesize($file_name));
			fclose($status_html_file);
		}
	}
	/////////////////////////////////////////////////////////////////

	$html = sprintf($html_template, $status_css, "Encomenda entregue!", $tracking_code, format_item_info($item['checkpoints']), $status_html);
	
	return $html;
}


function send_email($subject, $recipeint, $html_body, $text_body, $attachment = NULL) {
	$mail = new PHPMailer(true);

	try {
		//Server settings
		$mail->SMTPDebug = SMTP::DEBUG_SERVER;
		$mail->isSMTP();
		$mail->Host = EMAIL_HOST;
		$mail->SMTPAuth = true;
		$mail->Username = EMAIL_USER;
		$mail->Password = EMAIL_PASSWORD;
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		$mail->Port = 587;

		//Recipients
		$mail->setFrom(EMAIL_FROM, EMAIL_NAME);
		$mail->addAddress($recipeint);

		// Attachments
		if (isset($attachment)) {
			$mail->addStringAttachment($attachment, "encomenda-status.pdf");
		}

		// Content
		$mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body = $html_body;
		$mail->AltBody = $text_body;

		$mail->send();
		return true;
	} catch (Exception $e) {
		echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		return false;
	}
}


function html_to_pdf($html_code) {
	try {
		$html2pdf = new Html2Pdf();
		$html2pdf->writeHTML($html_code);
		$pdf_str = $html2pdf->output("out.pdf", "S");

		return $pdf_str;
	} catch (Html2PdfException $e) {
		$html2pdf->clean();

		$formatter = new ExceptionFormatter($e);
		echo $formatter->getHtmlMessage();
		return NULL;
	}
}

?>

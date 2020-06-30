<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Context;

if (CModule::IncludeModule("iblock") && $_REQUEST) {

//	Bitrix\Main\Diag\Debug::dumpToFile($_REQUEST);
//	Bitrix\Main\Diag\Debug::dumpToFile($_FILES);

	if ($_FILES) {
		$arr_file = array(
			"name" => $_FILES['FILE']["name"],
			"size" => $_FILES['FILE']["size"],
			"tmp_name" => $_FILES['FILE']["tmp_name"],
			"type" => $_FILES['FILE']["type"],
			"old_file" => "",
			"del" => "Y",
			"MODULE_ID" => "iblock");
		$file = CFile::SaveFile($arr_file, "forms_uploads");
		$file = CFile::MakeFileArray($file);
	}
	$fields = Context::getCurrent()->getRequest()->getPostList()->toArray();
	$fieldsToAdd = array();
	$fieldsToText = array();
	foreach ($fields as $key => $value) {
		$fields[$key] = htmlspecialchars($value);
		if ($key !== 'g-recaptcha-response' && $key !== 'token' && $value !== '') {
			if ($key !== 'IBLOCK_ID') {
				$fieldsToAdd["PROPERTY_VALUES"][$key] = array("VALUE" => htmlspecialchars($value));
				$properties = CIBlockProperty::GetList(array("sort" => "asc", "name" => "asc"), array("ACTIVE" => "Y", "CODE" => $key, "IBLOCK_ID" => $fields['IBLOCK_ID']));
				if ($prop_fields = $properties->GetNext()) {

					$fieldsToText[$prop_fields['CODE']] = $prop_fields["NAME"];
				}
			} else {
				$res = CIBlock::GetByID($value);
				if ($ar_res = $res->GetNext()) {
					$TitleToText = $ar_res["~NAME"];
				}

				$fieldsToAdd[$key] = htmlspecialchars($value);
			}
		}
	}
	$recaptcha = new \ReCaptcha\ReCaptcha(SECRET_KEY);
	$resp = $recaptcha->setExpectedHostname($_SERVER["SERVER_NAME"])
		->verify($fields["token"]);
	if ($resp->isSuccess()) {
		$el = new CIBlockElement;
		$fieldsToAdd["ACTIVE"] = 'N';
		$fieldsToAdd['NAME'] = date(DATE_RFC822);
		$fieldsToAdd["PROPERTY_VALUES"]['DATE'] = FormatDate("d.m.Y H:i:s", MakeTimeStamp(date()));
		$fieldsToAdd["PROPERTY_VALUES"]['FILE'] = array(
			"n0" => array("VALUE" => $file)
		);
		$TEXT = $TitleToText . PHP_EOL;
		foreach ($fieldsToText as $key => $value) {
			// $valueRadio = (is_numeric($fieldsToAdd["PROPERTY_VALUES"][$key]["VALUE"])) ? 'Да' : $fieldsToAdd["PROPERTY_VALUES"][$key]["VALUE"];
			$valueRadio = $fieldsToAdd["PROPERTY_VALUES"][$key]["VALUE"];
			$TEXT .= $value . ': ' . $valueRadio . PHP_EOL;
		}
		if ($PRODUCT_ID = $el->Add($fieldsToAdd)) {
			if ($TEXT) {
				CEvent::Send("CUSTOM_AJAX_FOR_ALL", "s1", array("FORMNAME" => $TitleToText, 'TEXT' => $TEXT));
			}
			echo('ok');

		} else {
			echo 'Error: ' . $el->LAST_ERROR;
		}

	} else {
		$errors = $resp->getErrorCodes();
	}
}
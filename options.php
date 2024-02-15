<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;

IncludeModuleLangFile(__FILE__);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if(check_bitrix_sessid()){
    //Обновление настроек
    if($_POST["type"] == "settings"){
        \Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "IBLOCK_ID", $_POST["IBLOCK_ID"]);
        \Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "BEST_PRICE_PROPERTY_ID", $_POST["BEST_PRICE_PROPERTY_ID"]);
        \Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "OLD_PRICE_PROPERTY_ID", $_POST["OLD_PRICE_PROPERTY_ID"]);
    }

    //Создание свойств IBL
    if($_POST["type"] == "iblockProperty"){
        $arFields = Array(
            "NAME" => "Старая цена (при установленной цене 'Лучшая цена')",
            "ACTIVE" => "Y",
            "SORT" => "100",
            "CODE" => "SW_RP_old_price",
            "PROPERTY_TYPE" => "S",
            "IBLOCK_ID" => \Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "IBLOCK_ID")
        );

        $ibp = new CIBlockProperty;
        $propID = $ibp->Add($arFields);

        if($propID){
            \Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "OLD_PRICE_PROPERTY_ID", $propID);
        }
        else{
            CAdminMessage::ShowMessage("Ошибка добавления свойства 'Старая цена': ".$ibp->LAST_ERROR);
        }


        $arFields1 = Array(
            "NAME" => "Лучшая цена",
            "ACTIVE" => "Y",
            "SORT" => "100",
            "CODE" => "SW_RP_best_price",
            "PROPERTY_TYPE" => "L",
            "IBLOCK_ID" => \Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "IBLOCK_ID"),
            "VALUES" => array(
                array( 
                    "VALUE" => "Нет",
                    "DEF" => "Y",
                    "SORT" => "100",
                    "XML_ID" => "N",
                ),
                array( 
                    "VALUE" => "Да",
                    "DEF" => "N",
                    "SORT" => "200",
                    "XML_ID" => "Y",
                ),
            ),
        );

        $ibp1 = new CIBlockProperty;
        $propID1 = $ibp1->Add($arFields1);

        if($propID1){
            \Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "BEST_PRICE_PROPERTY_ID", $propID1);
        }
        else{
            CAdminMessage::ShowMessage("Ошибка добавления свойства 'Лучшая цена': ".$ibp1->LAST_ERROR);
        }

    }
}

?>

<form action="" method="POST" style="display: flex; flex-direction:column; align-items: flex-start; gap: 10px;">
    <h2>Настройки модуля</h2>
    <?echo(bitrix_sessid_post());?>
    <input type="hidden" name="type" value="settings">

    <label for="">ID инфоблока товаров</label>
    <input type="text" name="IBLOCK_ID" value="<?=\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "IBLOCK_ID")?>">

    <label for="">ID свойства-метки "Лучшая цена"</label>
    <input type="text" name="BEST_PRICE_PROPERTY_ID" value="<?=\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "BEST_PRICE_PROPERTY_ID")?>">

    <label for="">ID свойства "Изначальная цена"</label>
    <input type="text" name="OLD_PRICE_PROPERTY_ID" value="<?=\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "OLD_PRICE_PROPERTY_ID")?>">


    <input type="submit" value="Сохранить настройки">
</form>

<form action="" method="POST" style="display: flex; flex-direction:column; align-items: flex-start; gap: 10px;">
    <h2>Свойства инфоблока</h2>
    <h3><b><i>ВНИМАНИЕ!</i></b> Модуль не проверяет существование старых полей и не удаляет не актульные свойства товара. Нажимать только для первичной инициализации свойств инфоблока.</h3>
    <?echo(bitrix_sessid_post());?>
    <input type="hidden" name="type" value="iblockProperty">
    <input type="submit" value="Создать свойства инфоблока">
</form>
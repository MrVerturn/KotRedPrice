<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;

IncludeModuleLangFile(__FILE__);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

//Нажата кнопка "Обновить" - происходит обновление цен на основе загруженного файла
if(($_POST["type"] == "price") && ($_POST["price_list"])){

    $arFile = \Bitrix\Main\UI\FileInput::prepareFile($_REQUEST["price_list"]);
    $tmpFilesDir = \CTempFile::GetAbsoluteRoot();

    if (isset($arFile['tmp_name']) && file_exists($tmpFilesDir.$arFile['tmp_name'])) {

        $arFile['tmp_name'] = $tmpFilesDir.$arFile['tmp_name'];

        $json_data = file_get_contents($arFile['tmp_name']);
        
        //Очистка загруженного файла от флага кодировки
        if(0 === strpos(bin2hex($json_data), 'efbbbf')) {
            $json_data = substr($json_data, 3);
        }

        $arrPriceList = json_decode($json_data, true);

        $hblInfo = HL\HighloadBlockTable::getById(\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "HBL_ID"))->fetch();
        $hblEntity = HL\HighloadBlockTable::compileEntity($hblInfo);
        $hblEntityDataClass = $hblEntity->getDataClass();

        //Очистка HLB от старых цен (Вызывается поштучно для запуска события OnDelete т.к. при удалении должны сбрасываться инзменения в товарах)
        $result = $hblEntityDataClass::getList(array(
            "select" => array("*"),
        ));
        
        while ($arRow = $result->Fetch())
        {
            $hblEntityDataClass::delete($arRow["ID"]);
        }

        //Запись данных из файлка в HLB
        $errorCount = 0;
        $successCount = 0;
        $totalCount = 0;
        $errors = "";

        if(is_array($arrPriceList)){
            foreach ($arrPriceList as $key => $dataRow) {
                $totalCount += 1;
                
                $dataLoad = [
                    "UF_XML_ID" => $dataRow["UID"],
                    "UF_TITLE" => $dataRow["Name"],
                    "UF_PRICE" => $dataRow["Price"]
                ];
        
                $res = $hblEntityDataClass::add($dataLoad);

                if($res->isSuccess()){
                    $successCount += 1;
                }
                else{
                    $errorCount += 1;
                    var_dump($res); echo "<br>"; echo "<br>";
                }

            }
        }

        $str = "Загрузка завершена<br><br>Всего найдено элементов: ". $totalCount."<br>Успешно загружено: ".$successCount."<br> Ошибок загрузки: ".$errorCount;

        if( $errorCount > 0 ){
            CAdminMessage::ShowMessage($str);
            ?>
                <div>
                    <div style="max-height: 500px; overflow-y: auto;" >
                        <?echo $errors;?>
                    </div>
                </div>
            <?                    
        }
        else{
            CAdminMessage::ShowNote($str);
        }
    }
}

?>

<form action="" method="POST" style="display: flex; flex-direction:column; align-items: flex-start; gap: 10px;">
    <h2>Загрузка цен</h2>
    <input type="hidden" name="type" value="price">
    <?
        echo \Bitrix\Main\UI\FileInput::createInstance(array(
            "name" => 'price_list',
            "description" => true,
            "upload" => true,
            "allowUpload" => "F",
            "maxCount" => 1,
            "medialib" => true,
            "fileDialog" => true,
            "cloud" => true,
            "delete" => true,
        ))->show(
            $fileParameters
        );
    ?>

    <input type="submit" value="Обновить">
</form>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
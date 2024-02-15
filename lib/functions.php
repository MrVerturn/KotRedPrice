<?php
 
namespace SigodinWeb;
 
use Bitrix\Highloadblock as HL; 


class Functions{
 
    //Обновление цены у товар
    static function savePrice($catalogGroupId,$price,$productId,$currency='RUB')
    {
        $res = array("OLD_PRICE" => "");

        $rsP = \Bitrix\Catalog\Model\Price::getList(array(
            'filter' => array('CATALOG_GROUP_ID'=>$catalogGroupId,'PRODUCT_ID'=>$productId),
        ));

        if($arP=$rsP->fetch())
        {
            $res["OLD_PRICE"] = $arP["PRICE"];

            if($price)
            {
                $result = \Bitrix\Catalog\Model\Price::update($arP['ID'],array(
                    'PRICE'=>$price,
                    'PRICE_SCALE'=>$price,
                    'CURRENCY'=>$currency,
                ));
            }
            else
            {
                $result = \Bitrix\Catalog\Model\Price::delete($arP['ID']);
            }
        }
        else
        {
            $result = \Bitrix\Catalog\Model\Price::add(array(
                'CATALOG_GROUP_ID'=>$catalogGroupId,
                'PRODUCT_ID'=>$productId,
                'PRICE'=>$price,
                'PRICE_SCALE'=>$price,
                'CURRENCY'=>$currency,
            ));            
        }
    
        if($result->isSuccess()) 
        {
            return array("status" => true, "result" => $res);
        }
        else
        {
            return array("status" => false, "result" => $result->getErrorMessages());
        }
    }

    //Получение строки с новой ценой и передача её для пременения
    static function applyRedPriceByRowID($hblRowId){

        $hlblock = HL\HighloadBlockTable::getById(\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "HBL_ID"))->fetch(); 
        $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 

        $item = $entity_data_class::getById($hblRowId)->Fetch();

        if($item){
            return \SigodinWeb\Functions::applyRedPrice($item);
        }

        return false;
    }

    //Применение "Красной цены"
    static function applyRedPrice($arrRedPrice){

        $ibl_id = \Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "IBLOCK_ID");

        //Находим товар и изменяем цену
        $iblock_class = \Bitrix\Iblock\Iblock::wakeUp($ibl_id)->getEntityDataClass();
        $elements = $iblock_class::getList([
            'select' => ["ID", "NAME", "SW_RP_OP_" => "SW_RP_old_price", "SW_RP_BP_" => "SW_RP_best_price"],
            'filter' => [
                'XML_ID' => $arrRedPrice["UF_XML_ID"],
            ],
            "limit" => 1 
        ])->fetch();

        //array("status" => true, "result" => array("OLD_PRICE" => 46.00));
        $res = \SigodinWeb\Functions::savePrice(1, $arrRedPrice["UF_PRICE"], $elements["ID"]);

        //Если изменнение произошло успешно то записываем информацию о старой цене в свойства
        if($res["status"]){
            $property_enums = \CIBlockPropertyEnum::GetList(
                Array("DEF"=>"DESC", "SORT"=>"ASC"), 
                Array("IBLOCK_ID"=>$ibl_id, "CODE"=>"SW_RP_best_price", 
                'XML_ID' => "Y"
            ));
            $propert_enum_id = $property_enums->fetch()["ID"];

            if( $elements["SW_RP_BP_VALUE"] !=  $propert_enum_id){

                $oldPrice = $res["result"]["OLD_PRICE"];

                $el = new \CIBlockElement;

                $res = \CIBlockElement::SetPropertyValuesEx(
                    $elements["ID"],
                    $ibl_id,
                    array(
                        "SW_RP_old_price" => $oldPrice,
                        "SW_RP_best_price" => $propert_enum_id,
                    ),
                );
            }
        }

        return true;
    }

    //Получение строки с новой ценой и передача её для удаления
    static function deleteRedPriceByRowID($hblRowId){
        $hlblock = HL\HighloadBlockTable::getById(\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "HBL_ID"))->fetch(); 
        $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 

        $item = $entity_data_class::getById($hblRowId)->Fetch();

        if($item){
            return \SigodinWeb\Functions::deleteRedPrice($item);
        }
        else{
            return false;
        }
    }

    //Удаление красной цены
    static function deleteRedPrice($arrRedPrice){
        $ibl_id = \Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "IBLOCK_ID");


        //Находим товар и получаем записанную в свойствах "Старую цену"
        $iblock_class = \Bitrix\Iblock\Iblock::wakeUp($ibl_id)->getEntityDataClass();
        $elements = $iblock_class::getList([
            'select' => ["ID", "NAME", "SW_RP_OP_" => "SW_RP_old_price", "SW_RP_BP_" => "SW_RP_best_price"],
            'filter' => [
                'XML_ID' => $arrRedPrice["UF_XML_ID"],
            ],
            "limit" => 1 
        ])->fetch();
      
        $property_enums = \CIBlockPropertyEnum::GetList(Array("DEF"=>"DESC", "SORT"=>"ASC"), Array("IBLOCK_ID"=>$ibl_id, "CODE"=>"SW_RP_best_price", 'XML_ID' => "Y"));
        $propert_enum_id = $property_enums->fetch()["ID"];

        //Если флаг "Красная цена используется" установлен то возвращаем старую цену и очищаем свойства

        if( $elements["SW_RP_BP_VALUE"] ==  $propert_enum_id){
            $res = \SigodinWeb\Functions::savePrice(1, $elements["SW_RP_OP_VALUE"], $elements["ID"]);
            $el = new \CIBlockElement;
            $res = \CIBlockElement::SetPropertyValuesEx(
                $elements["ID"],
                $ibl_id,
                array(
                    "SW_RP_old_price" => null,
                    "SW_RP_best_price" => null,
                ),
            );
        }

        return true;
    }

    //Запускается при удалении строки из HBL
    static function onDeleteRedPrice($event) {
        $id = $event->getParameter("id");
        if(is_array($id))
            $id = $id["ID"];
    
        \SigodinWeb\Functions::deleteRedPriceByRowID($id);
    }

    //Запускается при добавлении строки в HBL
    static function onAddRedPrice($event){
        $id = $event->getParameter("id");
        if(is_array($id))
            $id = $id["ID"];

        \SigodinWeb\Functions::applyRedPriceByRowID($id);
    }
 
    //Запускается перед сохранением цены товара
    static function onUpdateIBlockElementPriceCheckRedPrice(&$event){


        $fields = $event->getParameter("fields"); // получаем список полей   
        if(!\CModule::IncludeModule("iblock"))
            return; 

        //Получаем запись о товаре
        $res = \CIBlockElement::GetByID($fields["PRODUCT_ID"]);

        if($ar_res = $res->GetNext()){
                
            $xml_id = $ar_res["XML_ID"];

            //Ищем в HBL запись о текущем товаре
            $hlblock = HL\HighloadBlockTable::getById(\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "HBL_ID"))->fetch(); 
            $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
            $entity_data_class = $entity->getDataClass(); 
    
            $item = $entity_data_class::getList([
                'filter' => [
                    'UF_XML_ID' => $xml_id,
                ],
                'select' => [
                    '*',
                ],
            ])->Fetch();

            //Если запись есть и текущая цена не равна записанной в HBL
            if($item && ($item["UF_PRICE"] != $fields["PRICE"])){
                $ibl_id = \Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "IBLOCK_ID");

                //Находим ENUM_ID свойства "Флаг красной цены"
                $property_enums = \CIBlockPropertyEnum::GetList(Array("DEF"=>"DESC", "SORT"=>"ASC"), Array("IBLOCK_ID"=>$ibl_id, "CODE"=>"SW_RP_best_price", 'XML_ID' => "Y"));
                $propert_enum_id = $property_enums->fetch()["ID"];

                //Устанавливаем свойства сигнализирующие о присутствии красной цены
                $res = \CIBlockElement::SetPropertyValuesEx(
                    $fields["PRODUCT_ID"],
                    $ibl_id,
                    array(
                        "SW_RP_old_price" => $fields["PRICE"],
                        "SW_RP_best_price" => $propert_enum_id,
                    ),
                );

                //Изменяем обновляемую цену
                $fields["PRICE"] = $item["UF_PRICE"];
                $fields["PRICE_SCALE"] = $fields["PRICE"];
                $event->setParameter("fields",$fields);
                $changedFields = $fields;

                //Возвращаем список обновлённых полей
                $result = new \Bitrix\Main\Entity\EventResult;
                $result->modifyFields(["fields" => $changedFields]);
                return $result;
            }
        }
    }
 
    //Используется для тестов
    static function handler(&$event){
        \Bitrix\Main\Diag\Debug::dumpToFile($event, "Price::handler");
    }

}
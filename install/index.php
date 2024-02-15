<?
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;

Loc::loadMessages(__FILE__);

Class sigodinweb_redpriceloader extends CModule{
	var	$MODULE_ID = 'sigodinweb.redpriceloader';
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;

	function __construct(){
		$arModuleVersion = array();
		include(__DIR__.'/version.php');
		$this->MODULE_VERSION = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		$this->MODULE_NAME = Loc::getMessage('SW_RPL_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('SW_RPL_MODULE_DESC');

		$this->PARTNER_NAME = Loc::getMessage('SW_RPL_PARTNER_NAME');
		$this->PARTNER_URI = Loc::getMessage('SW_RPL_PARTNER_URI');

		$this->exclusionAdminFiles=array(
			'..',
			'.',
			'menu.php',
			'operation_description.php',
			'task_description.php'
		);
	}

	function InstallDB($arParams = array()){	
		Loader::IncludeModule('highloadblock');

		//Создаём HBL для загрузки цен

		$arLangs = Array(
			'ru' => 'Красные цены - Значения',
			'en' => 'Red Price - values'
		);

		$result = HL\HighloadBlockTable::add(array(
			'NAME' => 'SWRedPrice',
			'TABLE_NAME' => 'swredpricevalues', 
		));

		if ($result->isSuccess()) {
			$id = $result->getId();
			foreach($arLangs as $lang_key => $lang_val){
				HL\HighloadBlockLangTable::add(array(
					'ID' => $id,
					'LID' => $lang_key,
					'NAME' => $lang_val
				));
			}

			$arCartFields = Array(
				'UF_XML_ID'=>Array(
					'ENTITY_ID' => 'HLBLOCK_'.$id,
					'FIELD_NAME' => 'UF_XML_ID',
					'USER_TYPE_ID' => 'string',
					'MANDATORY' => 'Y',
					"EDIT_FORM_LABEL" => Array('ru'=>'UID товара', 'en'=>'ITEM UID'), 
					"LIST_COLUMN_LABEL" => Array('ru'=>'UID товара', 'en'=>'ITEM UID'),
					"LIST_FILTER_LABEL" => Array('ru'=>'UID товара', 'en'=>'ITEM UID'), 
					"ERROR_MESSAGE" => Array('ru'=>'', 'en'=>''), 
					"HELP_MESSAGE" => Array('ru'=>'', 'en'=>''),
				),
				'UF_TITLE'=>Array(
					'ENTITY_ID' => 'HLBLOCK_'.$id,
					'FIELD_NAME' => 'UF_TITLE',
					'USER_TYPE_ID' => 'string',
					'MANDATORY' => 'Y',
					"EDIT_FORM_LABEL" => Array('ru'=>'Название товара', 'en'=>'Item title'), 
					"LIST_COLUMN_LABEL" => Array('ru'=>'Название товара', 'en'=>'Item title'),
					"LIST_FILTER_LABEL" => Array('ru'=>'Название товара', 'en'=>'Item title'), 
					"ERROR_MESSAGE" => Array('ru'=>'', 'en'=>''), 
					"HELP_MESSAGE" => Array('ru'=>'', 'en'=>''),
				),
				'UF_PRICE'=>Array(
					'ENTITY_ID' => 'HLBLOCK_'.$id,
					'FIELD_NAME' => 'UF_PRICE',
					'USER_TYPE_ID' => 'integer',
					'MANDATORY' => 'Y',
					"EDIT_FORM_LABEL" => Array('ru'=>'Цена', 'en'=>'Price'), 
					"LIST_COLUMN_LABEL" => Array('ru'=>'Цена', 'en'=>'Price'),
					"LIST_FILTER_LABEL" => Array('ru'=>'Цена', 'en'=>'Price'), 
					"ERROR_MESSAGE" => Array('ru'=>'', 'en'=>''), 
					"HELP_MESSAGE" => Array('ru'=>'', 'en'=>''),
				),
			);

			$arSavedFieldsRes = Array();
			foreach($arCartFields as $arCartField){
				$obUserField  = new CUserTypeEntity;
				$ID = $obUserField->Add($arCartField);
				$arSavedFieldsRes[] = $ID;
			}

			\Bitrix\Main\Config\Option::set($this->MODULE_ID, "HBL_ID", $id);
		} else {
			$errors = $result->getErrorMessages();
		}
	}

	function UnInstallDB($arParams = array()){
		Loader::IncludeModule('highloadblock');
		Loader::IncludeModule('sigodinweb.redpriceloader');

		//Откатываем изменения в инфоблоке
		$hlblock = HL\HighloadBlockTable::getById(\Bitrix\Main\Config\Option::get("sigodinweb.redpriceloader", "HBL_ID"))->fetch(); 
        $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 

        $item = $entity_data_class::getList(array(
			'select' => array('*')
		));

		while($el = $item->fetch()){
            \SigodinWeb\Functions::deleteRedPrice($el);
		}

		//Удаляем HBL и настройки
		\Bitrix\Highloadblock\HighloadBlockTable::delete(\Bitrix\Main\Config\Option::get($this->MODULE_ID, "HBL_ID")); 
		\Bitrix\Main\Config\Option::delete($this->MODULE_ID);
	}

	function InstallEvents(){
		$eventManager = \Bitrix\Main\EventManager::getInstance(); 
		$eventManager->registerEventHandler("","SWRedPriceOnAfterAdd",$this->MODULE_ID,"SigodinWeb\\Functions","onAddRedPrice");
		$eventManager->registerEventHandler("","SWRedPriceOnBeforeDelete",$this->MODULE_ID,"SigodinWeb\\Functions","onDeleteRedPrice");
		$eventManager->registerEventHandler("catalog","\Bitrix\Catalog\Price::OnBeforeUpdate",$this->MODULE_ID,"SigodinWeb\\Functions","onUpdateIBlockElementPriceCheckRedPrice");
		return true;
	}

	function UnInstallEvents(){
		$eventManager = \Bitrix\Main\EventManager::getInstance(); 
		$eventManager->unRegisterEventHandler("","SWRedPriceOnAfterAdd",$this->MODULE_ID,"SigodinWeb\\Functions","onAddRedPrice");
		$eventManager->unRegisterEventHandler("","SWRedPriceOnBeforeDelete",$this->MODULE_ID,"SigodinWeb\\Functions","onDeleteRedPrice");
		$eventManager->unRegisterEventHandler("catalog","\Bitrix\Catalog\Price::OnBeforeUpdate",$this->MODULE_ID,"SigodinWeb\\Functions","onUpdateIBlockElementPriceCheckRedPrice");

		return true;
	}

	function InstallFiles($arParams = array()){
		$path = $this->GetPath()."/install/components";

		if (\Bitrix\Main\IO\Directory::isDirectoryExists($path)){
			CopyDirFiles($path, $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
		}

		if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath().'/admin')){
			CopyDirFiles($this->GetPath()."/install/admin/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin");
			if ($dir = opendir($path)){
				while (false !== $item = readdir($dir)){
					if (in_array($item, $this->exclusionAdminFiles))
						continue;
					file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.$item,
						'<'.'? require($_SERVER["DOCUMENT_ROOT"]."'.$this->GetPath(true).'/admin/'.$item.'");?'.'>');
				}
				closedir($dir);
			}
		}

		if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath().'/install/files')){
			$this->copyArbitraryFiles();
		}

		return true;
	}

	function UnInstallFiles(){
		\Bitrix\Main\IO\Directory::deleteDirectory($_SERVER["DOCUMENT_ROOT"].'/bitrix/components/'.$this->MODULE_ID.'/');

		if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath().'/admin')){
			DeleteDirFiles($_SERVER["DOCUMENT_ROOT"].$this->GetPath().'/install/admin/', $_SERVER["DOCUMENT_ROOT"].'/bitrix/admin');
			if ($dir = opendir($path)){
				while (false !== $item = readdir($dir)){
					if (in_array($item, $this->exclusionAdminFiles))
						continue;
					\Bitrix\Main\IO\File::deleteFile($_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.$this->MODULE_ID.'_'.$item);
				}
				closedir($dir);
			}
		}

		if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath().'/install/files')){
			$this->deleteArbitraryFiles();
		}

		return true;
	}

	function copyArbitraryFiles(){
		$rootPath = $_SERVER['DOCUMENT_ROOT'];
		$localPath = $this->GetPath().'/install/files';

		$dirIterator = new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $object){
			$destPath = $rootPath.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
			($object->isDir()) ? mkdir($destPath) : copy($object, $destPath);
		}
	}

	function deleteArbitraryFiles(){
		$rootPath = $_SERVER['DOCUMENT_ROOT'];
		$localPath = $this->GetPath().'/install/files';

		$dirIterator = new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $object){
			if (!$object->isDir()){
				$file = str_replace($localPath, $rootPath, $object->getPathName());
				\Bitrix\Main\IO\File::deleteFile($file);
			}
		}
	}

	function createNecessaryIblocks(){
		return true;
	}

	function deleteNecessaryIblocks(){
		return true;
	}

	function createNecessaryMailEvents(){
		return true;
	}

	function deleteNecessaryMailEvents(){
		return true;
	}

	function isVersionD7(){
		return CheckVersion(\Bitrix\Main\ModuleManager::getVersion('main'), '14.00.00');
	}

	function GetPath($notDocumentRoot = false){
		if ($notDocumentRoot){
			return str_ireplace(Application::getDocumentRoot(), '', dirname(__DIR__));
		}else{
			return dirname(__DIR__);
		}
	}

	function getSitesIdsArray(){
		$ids = Array();
		$rsSites = CSite::GetList($by = 'sort', $order = 'desc');
		while ($arSite = $rsSites->Fetch()){
			$ids[] = $arSite['LID'];
		}

		return $ids;
	}

	function DoInstall(){

		global $APPLICATION;
		if ($this->isVersionD7()){
			\Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

			$this->InstallDB();
			$this->createNecessaryMailEvents();
			$this->InstallEvents();
			$this->InstallFiles();


			//Значения свойств IBL для текущего сайта
			\Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "IBLOCK_ID", 23);
			\Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "BEST_PRICE_PROPERTY_ID", 1181);
			\Bitrix\Main\Config\Option::set("sigodinweb.redpriceloader", "OLD_PRICE_PROPERTY_ID", 1182);

		}else{
			$APPLICATION->ThrowException(Loc::getMessage('SW_RPL_INSTALL_ERROR_VERSION'));
		}

		$APPLICATION->IncludeAdminFile(Loc::getMessage('SW_RPL_INSTALL'), $this->GetPath().'/install/step.php');
	}

	function DoUninstall(){

		global $APPLICATION;

		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();

		$this->UnInstallFiles();
		$this->deleteNecessaryMailEvents();
		$this->UnInstallEvents();

		if ($request['savedata'] != 'Y')
			$this->UnInstallDB();

		\Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

		$APPLICATION->IncludeAdminFile(Loc::getMessage("SW_RPL_UNINSTALL"), $this->GetPath()."/install/unstep.php");
	}
}
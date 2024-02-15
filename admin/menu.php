<?php
$aMenu = array(    

    'parent_menu' => 'global_menu_services',
    'sort' => 150,
    'text' => "Красная цена",
    'title' => "Красная цена",
    'icon' => 'sale_menu_icon_statisti',
    'page_icon' => 'sale_menu_icon_statisti',
    'items_id' => 'sw_loadredprice',
    'url' => 'sw_loadredprice.php?lang='.LANGUAGE_ID,
);
    
return (!empty($aMenu) ? $aMenu : false);
?>
# Проект "Сытый Кот"

Задача: В 1С имеется тип цены "Красная цена" через который реализованы ручные скидки. Версия Bitrix малый бизнес. Необходимо создать функционал полуручной интеграции bitrix и 1C.  

Решение: Создание модуля "Красная цена". 
В состав модуля входит 3 блока: 
  1) Страница загрузки цен
  2) HighloadBlock для хранения загруженных цен
  3) 3 события, которые изменяют текущую цену товара

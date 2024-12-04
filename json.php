<?

$monthEn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthRu = ['Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня', 'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря'];

define('PAGE', !empty($_REQUEST['page']) ? $_REQUEST['page'] : 1);
define('LIMIT', !empty($_REQUEST['pageSize']) ? $_REQUEST['pageSize'] : 10);

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

use \Bitrix\Main\Data\Cache;

header('Content-Type: application/json');

$arSite = \Bitrix\Main\SiteTable::getById(SITE_ID)->fetch();

$cache = Cache::createInstance();
if ($cache->initCache(86400, "news_json-" . PAGE . '-' . LIMIT)) {
    $result = $cache->getVars();
} elseif ($cache->startDataCache()) {
    CModule::IncludeModule("iblock");
    $res = \Bitrix\Iblock\ElementTable::getList([
        'order' => [
            'ID' => 'ASC'
        ],
        'select' => [
            'ID',
            'CODE',
            'DETAIL_PAGE_URL' => 'IBLOCK.DETAIL_PAGE_URL',
            'PREVIEW_PICTURE',
            'NAME',
            'IBLOCK_SECTION_ID',
            'DATE_CREATE',
            'TAGS'
        ],
        'filter' => [
            'IBLOCK_ID' => 12,
            ">=DATE_CREATE" => '01.01.2015 00:00:00',
            "<=DATE_CREATE"   => '31.12.2015 23:59:59',
        ],
        // 'cache' => [
        //     'ttl' => 86400,
        //     'cache_joins' => true,
        // ],
        'offset' =>  PAGE != 1 ? (PAGE - 1) * LIMIT : 0,
        'limit' => LIMIT,
    ]);
    $result = [];
    while ($ob = $res->fetch()) {
        $result[$ob['ID']] = [
            'id' => $ob['ID'],
            'url' => str_replace('#CODE#', $ob['CODE'], str_replace('#SECTION_CODE_PATH#', $codeSection, str_replace('#SITE_DIR#', $arSite['DIR'] == '/' ? '' : $arSite['DIR'], $ob['DETAIL_PAGE_URL']))),
            'name' => $ob['NAME'],
            'date' => str_replace($monthEn, $monthRu, $ob['DATE_CREATE']->format("d M Y H:i")),
        ];
        $element = &$result[$ob['ID']];

        if (!empty($ob['PREVIEW_PICTURE']))
            $element['image'] = CFile::GetPath($ob['PREVIEW_PICTURE']);

        if (!empty($ob['IBLOCK_SECTION_ID'])) {
            $section = CIBlockSection::GetByID($ob["IBLOCK_SECTION_ID"])->fetch();
            $codeSection = $section['CODE'];
            $element['sectionName'] = $section['NAME'];
        }

        $prop = \Bitrix\Iblock\ElementPropertyTable::getList(["select" => ["VALUE"], "filter" => ["IBLOCK_ELEMENT_ID" => $ob["ID"]]]);
        if ($ob_prop = $prop->Fetch())
            $element['author'] = CIBlockElement::GetByID($ob_prop['VALUE'])->fetch()['NAME'];

        if (!empty($ob['TAGS']))
            $element['tags'] = explode(', ', $ob['TAGS']);
    }

    if (empty($result))
        $cache->abortDataCache();

    $cache->endDataCache($result);
}


echo json_encode($result);

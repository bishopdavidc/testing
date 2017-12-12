<?php

set_time_limit(0);
error_reporting(1);
ini_set('memory_limit', '-1');
ini_set('mysqli.reconnect', 1);
include_once('library/config.php');

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB2) or die("Could not connect to the Freegal Database at DB2");

$product_array = array('G010003169942P', 'G010003180885R', 'G010003138753F', 'G010002029460V', 'G0100011874473', 'G0100029796860',
    'G010003187618H', 'G0100012231435', 'G0100004876427', 'G0100007041287', 'G0100006701033', 'G0100029862982', 'G0100012230803',
    'G010003174496F', 'G010001700105T', 'G010001413301O', 'G010001717215F', 'G0100031777196', 'G0100031477333', 'G010000917091B',
    'G010000344960Q', 'G010000536639X', 'G010002955020Q', 'G010002009997L', 'G0100007045636', 'G010001413037O', 'G0100012227704',
    'G0100009139649', 'G010001396335J', 'G0100031316049', 'G010000935705D', 'G010000669906X', 'G0100009346114', 'G010001716416D',
    'G010001910872K', 'G0100012224260', 'G010000279921A', 'G0100003449698', 'G010001222567A', 'G010000279064N', 'G010000911294F',
    'G010000476858L', 'G010000515270U', 'G010002900286R', 'G0100011857750', 'G0100020280701', 'G0100031946901', 'G0100032020668',
    'G0100030596525', 'G010000907162C', 'G010001793298K', 'G0100006696618', 'G010000911261X', 'G010003197443O', 'G010002031812Z',
    'G010001885919V', 'G0100029367242', 'G0100027604740', 'G010003178719P', 'G010002973617A', 'G0100012622967', 'G010001873652G',
    'G010002031765F', 'G010002643753S', 'G010003084897L', 'G010000934809D', 'G010003111369Q', 'G0100005287076', 'G0100014153711',
    'G010003185113T', 'G010003183696K', 'G010003172826E', 'G010003170319I', 'G010003210520T', 'G010003064808G', 'G010000427752V',
    'G010001887576N', 'G0100004166649', 'G0100003446008', 'G0100031315610', 'G0100031570793', 'G010003211904M');

foreach ($product_array as $product)
{
    updateDB($product, $db);
}

function updateDB($prod_id, $db)
{
   echo $query = "select 
                ProductID, group_concat(ProdID) as prod_ids
            from
                freegal.Songs
            where
                ProductID='$prod_id' and provider_type='sony'
            order by ProdID desc";
    $resource = mysqli_fetch_assoc(mysqli_query($db, $query));

    $territories_list = array('US', 'AU', 'CA', 'IT', 'NZ', 'GB', 'IE', 'BM', 'DE');
    foreach ($territories_list as $territory)
    {
        $ter = strtolower($territory);
        $update_query = "UPDATE {$ter}_countries set DownloadStatus=1 , StreamingStatus=1  where provider_type='sony' and ProdID in (" . $resource['prod_ids'] . ")";
      
        mysqli_query($db, $update_query);
    }
}

<?php

require_once('vendor/autoload.php');

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

// environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();


// vowel domain and cookie value
if (isset($_ENV["VOWEL_SUBDOMAIN"])) {
    $subdomain = $_ENV["VOWEL_SUBDOMAIN"];
} else {
    exit("VOWEL_SUBDOMAIN is missing.");
}
if (isset($_ENV["COOKIE_VSC1_VALUE"])) {
    $vsc1 = $_ENV["COOKIE_VSC1_VALUE"];
} else {
    exit("COOKIE_VSC1_VALUE is missing.");
}

// setup the cookie jar
$jar = new \GuzzleHttp\Cookie\CookieJar;
$jar = \GuzzleHttp\Cookie\CookieJar::fromArray(
    [
        'VSC1' => $vsc1,
    ],
    $subdomain . '.vowel.com'
);

print("========================================================\n");
print("Welcome to a hastily created Vowel Downloader v1 7/4/23\n");
print("Using the account at https://$subdomain.vowel.com/\n");
print("Looking...\n");


$downloadpath = getcwd() . "/media/";

$curlies = [];


// try setup the request
try {

    // get all the channels (folders) you have access to, use the cookie jar from earlier
    $vd_client = new \GuzzleHttp\Client();

    $vd_response = $vd_client->request(
        'GET',
        'https://' . $subdomain . '.vowel.com/api/channels/?size=1000',
        [
            'cookies' => $jar
        ]
    );

    $vd_response_code = $vd_response->getStatusCode();
    if ($vd_response_code == 200) {
        
        // get all the channels (folders)
        $vd_response_body = json_decode($vd_response->getBody()->getContents());
        $channels = $vd_response_body->_embedded->channels;
        $channel_count = $vd_response_body->page->totalElements;
        echo "Found $channel_count channel folders, processing...\n";
        $i=1;


        // go through each channel please
        foreach ($channels as $channel) {
            
            $downloadfile = preg_replace('/[^A-Za-z0-9\- ]/', '', "$channel->name-$channel->id");
            $channel_folder = "$downloadpath$downloadfile";
            echo "$i/$channel_count $channel->name\n";


            // get all the media in this folder and try to download them
            $channelid = $channel->id;

            try {

                $vd_client2 = new \GuzzleHttp\Client();
                $vd_response2 = $vd_client2->request(
                    'GET',
                    'https://' . $subdomain . '.vowel.com/api/channels/'.$channelid.'/media?size=1000',
                    [
                        'cookies' => $jar
                    ]
                );            

                $vd_response_code2 = $vd_response2->getStatusCode();

                if ($vd_response_code2 == 200) {
                    $vd_response_body2 = json_decode($vd_response2->getBody()->getContents());
                    $medias = $vd_response_body2->_embedded->media;
                    $medias_count = $vd_response_body2->page->totalElements;
                    echo "Found $medias_count media items, processing... ";
                    $j=1;
                    foreach ($medias as $media) {
                            $media_file_name = date("Y-m-d", strtotime($media->startTime))."-".$media->name."-".$media->id.".mp4";
                            $media_file_name = preg_replace('/[^A-Za-z0-9\.\- ]/', '', $media_file_name);
                            $media_file_name = preg_replace('/[ ]/', '-', $media_file_name);
                            $channel_folder = preg_replace('/[ ]/', '-', $channel_folder);
                            $curlparms=[];
                            $curlparms["location"] = "https://$subdomain.vowel.com/api/meetings/$media->id/download";
                            $curlparms["output"] = "$channel_folder/$media_file_name";
                            $curlies[]=$curlparms;
                            echo "$j ";                 
                            $j++; 
                    }
                    echo " done.\n";

                }
                
                //increment channel number
                $i++;

            } catch (ClientException $e) {

                echo Psr7\Message::toString($e->getRequest());
                echo Psr7\Message::toString($e->getResponse());


            }

        }
        
        
        $curl_base = "curl -C - --parallel --create-dirs -H 'cookie: VSC1=$vsc1;' ";

        $num_simul = 10;
        $curl_downloads = "";
        $k = 0;

        foreach ($curlies as $parm) {
            
            $curl_downloads .= " --location ".$parm['location']." --output '".$parm['output']."'";
            $k++;

            if ($k % $num_simul == 0 || $k == count($curlies)) {
                file_put_contents("vd.sh", "echo 'Starting $num_simul downloads...'\n$curl_base $curl_downloads\n", FILE_APPEND);
                $curl_downloads = "";
            }

        }

        echo "Done.\nNow, you just have to run vd.sh and wait.\nDon't forget to chmod +x that file.\n";

    }


} catch (ClientException $e) {

    echo Psr7\Message::toString($e->getRequest());
    echo Psr7\Message::toString($e->getResponse());


}
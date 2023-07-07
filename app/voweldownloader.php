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

echo "========================================================\n";
echo "Welcome to a hastily created Vowel Downloader v1 7/4/23\n";
echo "Using the account at https://$subdomain.vowel.com/\n";
echo "Looking...\n";


$downloadpath = getcwd() . "/media/";

$curlies = [];


// try setup the request
try {

    // get all the channels (folders) you have access to, use the cookie jar from earlier
    echo "Getting all the channels...\n";
    $vd_client = new \GuzzleHttp\Client();

    $vd_response = $vd_client->request(
        'GET',
        'https://' . $subdomain . '.vowel.com/api/channels/?size=10000',
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

        $channel_mediaids = [];


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
                        if ($media->isRecordingDownloadAvailable==true && $media->mediaType=="Meeting") {

                            $media_file_name = date("Y-m-d", strtotime($media->startTime))."-".$media->name."-".$media->id.".mp4";
                            $media_file_name = preg_replace('/[^A-Za-z0-9\.\- ]/', '', $media_file_name);
                            $media_file_name = preg_replace('/[ ]/', '-', $media_file_name);
                            $channel_folder = preg_replace('/[ ]/', '-', $channel_folder);
                            $curlparms=[];
                            $channel_mediaids[] = $media->id;
                            $curlparms["location"] = "https://$subdomain.vowel.com/api/meetings/$media->id/download";
                            $curlparms["output"] = "$channel_folder/$media_file_name";
                            $curlies[]=$curlparms;
                            echo "$j ";                 
                            $j++; 

                        }

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


    }



    // get all the meetings and filter out all of those that are in folders
    echo "Getting all the meetings...\n";
    $vdm_client = new \GuzzleHttp\Client();

    $vdm_response = $vdm_client->request(
        'GET',
        'https://' . $subdomain . '.vowel.com/api/meetings/?size=10000',
        [
            'cookies' => $jar
        ]
    );

    $vdm_response_code = $vdm_response->getStatusCode();
    if ($vdm_response_code == 200) {

        // get all the channels (folders)
        $vdm_response_body = json_decode($vdm_response->getBody()->getContents());
        $meetings = $vdm_response_body->_embedded->media;
        $meeting_count = $vdm_response_body->page->totalElements;
        echo "Found $meeting_count meetings in total.\nLet's see which are available to download, not in a folder...\n";
        $l = 1;

        foreach ($meetings as $meeting) {
            // is it available?
            if ($meeting->isRecordingDownloadAvailable==true) {

                if (!in_array($meeting->id, $channel_mediaids)) {
                    // if we don't find this meeting in our list, we should add it with a generic folder
                    $media_file_name2 = date("Y-m-d", strtotime($meeting->startTime))."-".$meeting->name."-".$meeting->id.".mp4";
                    $media_file_name2 = preg_replace('/[^A-Za-z0-9\.\- ]/', '', $media_file_name2);
                    $media_file_name2 = preg_replace('/[ ]/', '-', $media_file_name2);
                    $curlparms=[];
                    $curlparms["location"] = "https://$subdomain.vowel.com/api/meetings/$meeting->id/download";
                    $curlparms["output"] = "$downloadpath/No-Folder-Found/$media_file_name2";
                    $curlies[]=$curlparms;
                    echo "$l ";                 
                    $l++; 

                }

            }

        }


    }      

    echo " additional videos found that were not in a folder.\n"  ;
    

    // write out the bash file
    $curl_base = "curl -C - --parallel --create-dirs -H 'cookie: VSC1=$vsc1;' ";

    $num_simul = 10;
    $curl_downloads = "";
    $k = 0;
    $total_videos = count($curlies);
    if (file_exists("vd.sh")) {
        unlink("vd.sh");    
    }
    file_put_contents("vd.sh", "echo 'Downloading $total_videos Vowel videos in batches of $num_simul at a time...'\n", FILE_APPEND);

    foreach ($curlies as $parm) {
        
        $curl_downloads .= " --location ".$parm['location']." --output '".$parm['output']."'";
        $k++;

        if ($k % $num_simul == 0 || $k == count($curlies)) {
            file_put_contents("vd.sh", "$curl_base $curl_downloads\n", FILE_APPEND);
            $curl_downloads = "";
            file_put_contents("vd.sh", "echo 'Completed $k of $total_videos.'\n", FILE_APPEND);
        }


    }

    file_put_contents("vd.sh", "echo 'Congrats, you should have all your videos now. Peace!'\n", FILE_APPEND);
    chmod("vd.sh", 0755);


    echo "Done.\nNow, you just have to run vd.sh and wait.\n";    


} catch (ClientException $e) {

    echo Psr7\Message::toString($e->getRequest());
    echo Psr7\Message::toString($e->getResponse());


}
# voweldownloader

Tiny PHP App that downloads all Vowel videos and reproduces their folders

## Installation

You'll need PHP 8 locally and composer. Be sure you've already installed composer (https://getcomposer.org/) or you won't be able to run the app.

Clone this repo and run `composer install` in `app/` directory and you should be golden.

This is a PHP 8 command line app that creates an executable bash script `vd.sh` that can be used to actually download all Vowel videos to your computer. The bash script uses `curl` to parallelize multiple downloads at a time. There is no UI.

## Configuration

You will need to copy the `.env.example` file to `.env` and fill in the variables `VOWEL_SUBDOMAIN` and `COOKIE_VSC1_VALUE` -- more info on what these are is at https://ivn.st/vdvar


## Usage

Once you've installed and configured, `cd` into the `app` folder and run `php voweldownloader.php` which will generate the `vd.sh` file that contains all the `curl` commands that actually download the files. Be sure to `chmod +x vd.sh` so that you can execute the script with `./vd.sh` - let this command run for as long as it needs to. It will connect to your Vowel account, determine all the channels (folders) that you have access to, find all the media in those channels, and then generate `vd.sh` that you will need to run to actually download the files. The `vd.sh` bash script will put files in a new `media/` folder of wherever you cloned this repo to.

## Attribution

This was created by Ivan Stegic (https://ivn.st/abt) to save the last six months of meetings after Vowel abruptly shut down. I hope this helps you, reach out and I might be able to help.


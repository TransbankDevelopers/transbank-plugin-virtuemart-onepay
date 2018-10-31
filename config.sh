#!/usr/bin/env bash

#Script for configure the plugin project
PHP_SDK_VERSION="1.4.1"
REPO_SDK="https://github.com/TransbankDevelopers/transbank-sdk-php/archive/$PHP_SDK_VERSION.zip"
DIR_LIBS="src/transbank_onepay/library"
DIR_NAME_SDK="transbank-sdk-php"
DIR_DEST_SDK="$DIR_LIBS/$DIR_NAME_SDK"

echo "Removing the older SDK $DIR_DEST_SDK"
rm -rf $DIR_DEST_SDK**

echo "Downloading SDK version: $PHP_SDK_VERSION from: $REPO_SDK"
curl -O -L $REPO_SDK
unzip "$PHP_SDK_VERSION.zip" -d $DIR_LIBS
rm -rf "$PHP_SDK_VERSION.zip"
mv "$DIR_DEST_SDK-$PHP_SDK_VERSION" "$DIR_DEST_SDK"

echo "Remove webpay sdk, is not necessary"
sed -i.bkp '/lib\/webpay/d' "$DIR_DEST_SDK/init.php"

echo "SDK version: $PHP_SDK_VERSION"

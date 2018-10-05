#!/usr/bin/env bash

#Script for configure the plugin project

PHP_SDK_VERSION='1.3.2'
REPO_SDK="https://github.com/TransbankDevelopers/transbank-sdk-php/archive/$PHP_SDK_VERSION.zip"
DIR_DEST_SDK='src/library/transbank-sdk-php'

echo "Removing the older SDK"
rm -rf "$DIR_DEST_SDK**"

echo "Downloading SDK version: $PHP_SDK_VERSION"
wget $REPO_SDK -O "$PHP_SDK_VERSION.zip"
unzip "$PHP_SDK_VERSION.zip" -d src/library
rm -rf "$PHP_SDK_VERSION.zip"
mv "$DIR_DEST_SDK-$PHP_SDK_VERSION" $DIR_DEST_SDK

echo "Changing to SDK version: $PHP_SDK_VERSION"
echo "SDK version: $PHP_SDK_VERSION"

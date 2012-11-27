#!/bin/sh
#This script runs from the forum wwwroot. Only mkdir and cp are available.

mkdir -p images/xbmc
cp -rf xbmc/images/* images/xbmc/
cp -rf xbmc/plugins/* inc/plugins/
cp -rf xbmc/languages/* inc/languages/


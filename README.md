What it does
============
In a few words "why" :
======================

The main API server is applying sanity restrictions in order to limit per IP, 
per area or per elapsed time calls sent to it. This simple proxy is reducing 
such calls by answering the data request calls itself.

In a few words "how" :
======================

When handeling a request,  any of map, node, way, relation, nodes, ways, 
relations and capabilities GET calls are converted to the overpass API 
syntaxe and forwarded to a local (or even could do to a distant) overpass
API server.
When handeling any other PUT, POST, DELETE, WHATEVER or GET requests, it 
forwards the call to the 0.6 API dev server acting as a transparent proxy as 
much as possible.
Advantage beeing that no modifications are needed in the client if it supports 
the 0.6 API (beside changing the target URL of course)

note: Your credentials are clear text transmitted to the proxy server


Installation
============
Requirements
============

- Tested with php 5.3
- curl + php-curl module
- Tested with apache2+ with mod_rewrite activated
- git to retrieve latestest program code from github

On debian/ubuntu this command should get you what you need :

``
apt-get install apache2 php5-curl libapache2-mod-php5 git
``

configuration
=============
- Create a Virtualhost for apache (or use the default one in localhost) and go to that directory and git clone the code into
a directory named "api" (I don't remember well if the code is ready to work with any directory name but I guess that no)
this way :

``
git clone git://github.com/osm-fr/simple_api_proxy.git api
``

- Enable mod_rewrite in apache 

``
a2enmod rewrite
``

- copy the config-sample.php file to config.php
- Edit the config.php file and choose your Overpass_API server URL and a few settings
- Change the osm's API 0.6 server URL to the real (non dev one) if you whant to use it for real

Use http://your-host/api as the URL to request osm data

More information
================
You can also have more information about the live working instance by
reading :
http://wiki.openstreetmap.org/wiki/Servers/api.openstreetmap.fr

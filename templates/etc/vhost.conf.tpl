<VirtualHost *:80>
	DocumentRoot #phing:paths.public#
	ServerName #phing:hosts.public#
	ErrorLog #phing:paths.logs#/#phing:hosts.public#-error_log
	CustomLog #phing:paths.logs#/#phing:hosts.public#-access_log common

	<Directory "#phing:paths.public#">
		DirectoryIndex index.php index.html
		AllowOverride FileInfo AuthConfig Limit Indexes Limit
		Options FollowSymLinks Indexes
		Options +Includes
		AddType application/x-httpd-php .php
	</Directory>
</VirtualHost>
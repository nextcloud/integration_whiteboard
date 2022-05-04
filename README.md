# Whiteboard integration in Nextcloud

🖵 Nextcloud Whiteboard, powered by [Spacedeck](https://github.com/spacedeck/spacedeck-open).

This app integrates Spacedeck whiteboard server. It lets Nextcloud users create `.whiteboard` files
which can then be opened in the Files app and in Talk. Those files can be shared to other users
or via public links. Everyone having access with write permissions to such a file can edit it collaboratively.

⚠ This app is still experimental and can fail in some custom Nextcloud setups.

# 🛠 Install

There are 2 ways to setup this app:
* Use the bundled Spacedeck server (only works on 64 bits GNU/Linux servers)
* [Deploy a standalone Spacedeck server](#deploy-spacedeck)

This is recommended to deploy and use a standalone Spacedeck server for:
* Better performances
* Real time collaboration (Websocket communication)
* The ability to run the Spacedeck server on another system than Nextcloud
* Being able to run Spacedeck on non-x64 architecture (like arm64 with a RaspberryPi)

Spacedeck has a few **optional** requirements to be able to convert media files:
* `graphicsmagick` to convert images
* `ffmpeg` to convert audio and video files
* `ghostscript` for pdf import

To use the bundled Spacedeck server, just install the app and you're good to go.

Follow [these instructions](#deploy-spacedeck) to deploy a standalone Spacedeck server.

# 𝄘 Features

* Draw
    * Draw lines and shapes
    * Write text
    * Add images, audio files, videos files and PDFs
* Collaborate
    * Create zones
    * Presenter mode (others follow your movements)
    * Show participants mouse cursors
* Share
    * Share to a Talk room
    * Share to users
    * Share via public links

# ⚠ Limitations

* Spacedeck provides a Pdf export feature. It does not work with the bundle Spacedeck server. Use a standalone server if you want this feature.
* Video media actions are not transmitted in presenter mode. This is a limitation of Spacedeck.
* Files version restoration does not work for the moment.
* Overwritting a whiteboard file will not update the board (space) content.
* Medias are not saved in `.whiteboard` files. They are lost when copying a whiteboard file on another Nextcloud instance.

# 👀 Screenshots

![screenshot](https://github.com/eneiluj/integration_whiteboard/raw/master/img/screenshot1.jpg)

# Deploy Spacedeck

Spacedeck can be deployed on the same system hosting your Nextcloud instance or on a different system.

In order to deploy a standalone Spacedeck server, you will be guided into those steps:

* Get Spacedeck sources
* Configure Spacedeck
* Launch Spacedeck server
* Create a Spacedeck user for Nextcloud
* Optionally make Spacedeck accessible via a reverse proxy in a virtual host
* Configure the Nextcloud whiteboard integration app to use your standalone Spacedeck server
* Check your setup

Requirements on the system hosting Spacedeck:
* Node >= 10.0
* Npm >= 7.0.0

## Get Spacedeck sources

``` bash
git clone https://github.com/eneiluj/spacedeck-open -b ext-access-control
cd spacedeck-open
npm install
```

## Configure Spacedeck

Go to the "Connected accounts" section of your Nextcloud admin settings. Find the Spacedeck integration settings.
Uncheck "Use integrated Spacedeck server". Copy the "ext_access_control" suggested by the hints. This should look like:
```
https://YOUR.NEXTCLOUD.ORG/index.php/apps/integration_whiteboard/session/check
```

Edit the `spacedeck-open/config/default.json` file and set the "ext_access_control" value with the one you copied before.
This enables Nextcloud to manage permissions on the boards.

You can also adjust other config values to your convenience:
* `port`: TCP port Spacedeck server will listen to
* `invite_code`: Secret code used to create users in Spacedeck (Only one user will be created in our case)

Change the other values only if you know what you are doing.

## Launch Spacedeck

Then start the Spacedeck server:
``` bash
npm run start
```
and browse the Spacedeck web interface.

## Create a Spacedeck user for Nextcloud

A single Spacedeck user is required by Nextcloud to create and edit boards. We will create this user
manually and set an "API token" for Nextcloud.

* Click the "Signup" button on the top right corner
* Use the `invite_code` to create a Spacedeck user
* Once you are logged in, click on the buddy icon on the top right corner and go to "Edit account"
* In the profile tab, set your "API token". Make sure your API token is more than 10 characters long
* Hit the "Save" button
* Logout

## Optionally make Spacedeck accessible via a reverse proxy in a virtual host

Your Spacedeck server must be accessible by the browsers of your users **and** by your Nextcloud server so you might
need a reverse proxy to make it possible to reach it through your webserver.

⚠ **Warning**: Spacedeck only works if it is reachable at the root path. Make sure you don't use a sub path in your
virtual host.

Here is an example of Apache virtual host proxying to Spacedeck (with Websocket support).
This make Spacedeck (which is listening to the 9666 port) to be accessible at https://spacedeck.myserver.org
```apacheconf
<VirtualHost *:443>
        ServerName spacedeck.myserver.org
        ProxyPreserveHost On
        ProxyPass  / http://localhost:9666/
        ProxyPassReverse / http://localhost:9666/
        ProxyPreserveHost On

        # only if you want to provide an HTTPS access to Spacedeck (highly recommended)
        SSLProxyEngine On
        SSLProxyVerify none
        SSLProxyCheckPeerCN off
        SSLProxyCheckPeerName off
        SSLProxyCheckPeerExpire off
        SSLCertificateFile /etc/letsencrypt/live/myserver.org/fullchain.pem
        SSLCertificateKeyFile /etc/letsencrypt/live/myserver.org/privkey.pem

        ProxyPassMatch "/socket" ws://localhost:9666/socket nocanon
        ProxyPassMatch "/socket/" ws://localhost:9666/socket/

        RewriteEngine On
        RewriteCond %{HTTP:Upgrade} =websocket
        RewriteRule /(.*)           ws://localhost:9666/socket [P,L]
        RewriteCond %{HTTP:Upgrade} !=websocket
        RewriteRule /(.*)           http://localhost:9666/$1 [P,L]
</VirtualHost>
```

## Configure the Nextcloud whiteboard integration app

Get back to the "Connected accounts" section of your Nextcloud admin settings.
Enter the Spacedeck server URL and the API token you have set for your Spacedeck user.

Reminder: Your Spacedeck server must be accessible by the browsers of your users **and** by your Nextcloud server.

## Check your setup

Press "Check Spacedeck config" to make sure you can reach Spacedeck with your browser and that your Nextcloud
server can access Spacedeck's API.
If the check is successful, you are ready to use the Spacedeck integration.
Go to the Files app and create a new whiteboard file.

# Whiteboard integration in Nextcloud

🖵 Nextcloud Whiteboard, powered by [Spacedeck](https://github.com/spacedeck/spacedeck-open).

This app integrates Spacedeck whiteboard server. It lets Nextcloud users create `.whiteboard` files
which can then be opened in the Files app and in Talk. Those files can be shared to other users
or via public links. Everyone having access with write permissions to such a file can edit it collaboratively.

⚠ This app is still experimental and can fail in some custom Nextcloud setups.

# 🛠 Install

This app works on 64 bits GNU/Linux servers.

Spacedeck has a few **optional** requirements to be able to convert media files:
* `graphicsmagick` to convert images
* `ffmpeg` to convert audio and video files
* `ghostscript` for pdf import

As the app includes a bundled Spacedeck server, just install this app in Nextcloud app settings.

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

* Spacedeck provides a Pdf export feature. It does not work in this app for the moment.
* Video media actions are not transmitted in presenter mode. This is a limitation of Spacedeck.
* Files version restoration does not work for the moment.
* Medias are not saved in `.whiteboard` files. They are lost when copying a whiteboard file on another Nextcloud instance.

# 👀 Screenshots

![screenshot](https://github.com/eneiluj/integration_whiteboard/raw/master/img/screenshot1.jpg)
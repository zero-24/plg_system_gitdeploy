# GitDeploy Plugin

This plugin allows to automaticly deploy changes from a git repo and is based on [KickDeploy](https://github.com/nielsnuebel/kickdeploy)

## Sponsoring and Donation

You want to support my work for the [development of my extensions](https://extensions.joomla.org/profile/profile/details/200189/) and my work for the [Joomla! Project](https://volunteers.joomla.org/joomlers/248-tobias-zulauf) you can give something back and sponsor me.

There are two ways to support me right now:
- This extension is part of [Github Sponsors](https://github.com/sponsors/zero-24/) by sponsoring me, you help me continue my oss work for the [Joomla! Project](https://volunteers.joomla.org/joomlers/248-tobias-zulauf), write bug fixes, improving features and maintain my extensions.
- You just want to send me an one-time donation? Great you can do this via [PayPal.me/zero24](https://www.paypal.me/zero24).

Thanks for your support!

## Features

This plugin allows to listen on github hooks and than deploy changes from a git repo.
-

## Configuration

### Initial setup the plugin

- [Download the latest version of the plugin](https://github.com/zero-24/plg_system_gitdeploy/releases/latest)
- Install the plugin using `Upload & Install`
- Enable the plugin `System - GitDeploy` from the plugin manager
- Setup the groups that you want 2FA to be enforced one.

Now the inital setup is completed.

### Option descriptions

#### 2FA Usergroups

Select form a multiselect the groups on where the 2FA should be enforced on the next login.

## Update Server

Please note that my update server only supports the latest version running the latest version of Joomla and atleast PHP 7.2.5.
Any other plugin version I may have added to the download section don't get updates using the update server.

## Issues / Pull Requests

You have found an Issue, have a question or you would like to suggest changes regarding this extension?
[Open an issue in this repo](https://github.com/zero-24/plg_system_gitdeploy/issues/new) or submit a pull request with the proposed changes.

## Translations

You want to translate this extension to your own language? Check out my [Crowdin Page for my Extensions](https://joomla.crowdin.com/zero-24) for more details. Feel free to [open an issue here](https://github.com/zero-24/plg_system_gitdeploy/issues/new) on any question that comes up.

## Joomla! Extensions Directory (JED)

This plugin can also been found in the Joomla! Extensions Directory: [GitDeploy by zero24](https://extensions.joomla.org/extension/gitdeploy/)

## Release steps

- `build/build.sh`
- `git commit -am 'prepare release GitDeploy 1.0.x'`
- `git tag -s '1.0.x' -m 'GitDeploy 1.0.x'`
- `git push origin --tags`
- create the release on GitHub
- `git push origin master`

## Crowdin

### Upload new strings

`crowdin upload sources`

### Download translations

`crowdin download --skip-untranslated-files --ignore-match`

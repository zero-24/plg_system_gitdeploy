# GitDeploy Plugin

This plugin allows to automaticly deploy changes from a git repo and is based on [KickDeploy](https://github.com/nielsnuebel/kickdeploy)

## Features

This plugin allows to listen on github hooks and than deploy changes from a git repo.

## Configuration

### Initial setup the plugin

* [Download the latest version of the plugin](https://github.com/zero-24/plg_system_gitdeploy/releases/latest)
* Install the plugin using `Upload & Install`
* Enable the plugin `System - GitDeploy` from the plugin manager
* Register / Log into [github.com](https://github.com/login)
* Create an repo on [github.com](https://docs.github.com/en/free-pro-team@latest/github/getting-started-with-github/create-a-repo)
* Now go to your server and [clone that repo into a folder of your choice](https://docs.github.com/en/free-pro-team@latest/github/creating-cloning-and-archiving-repositories/cloning-a-repository)
* Please make sure you have added the [remote repo](https://docs.github.com/en/free-pro-team@latest/github/using-git/adding-a-remote)
* Setup an [webhook for your repo on github.com](https://docs.github.com/en/free-pro-team@latest/developers/webhooks-and-events/creating-webhooks)
* Set the `Payload URL` to `https://www.example.org?github=true&targetSite=examle.org` (targetSite is optional and will default to the current domain)
* Generate and enter an secret value only known by GitHub and you.
* Now go to the plugin options:
  * `Git Path`: Please copy here the result of `which git` when you run that from your server.
  * `Git Repo`: Please set the repo name here `octocat/hello-world`
  * `Branch`: Please set the branch name you want to pull from
  * `Remote`: Please set the name of the remote you configured
  * `Use Hook-Secret` & `Hook-Secret`: Please enable this option and set the secret configured in the webhook
  * `Run cd` & `cd Path`: You can enable this option when the git folder is not the root folder of joomla
  * `Run git reset`: You can enable this option to run an git reset before pulling the changes
* Please switch to the `Notifications` tab in the options
  * `Notifications`: You can enable notifications
  * `Notifications Provider`: Please select an notification provider where we should the notification to.
  * When you have selected we show you the fields we need to use that provider please set the correct data here.
* Plese save & close the plugin
* Now commit the inital code or change a file
* GitHub now sends you the webhook and the plugin executes git pull as well as sends you a notification.

Now the inital setup is completed.

### Additional remarks

#### Suggested .htaccess rule

When you git repo is directly accessible to the web i would suggest to deny the access to the .git folder via an .htaccess file
```
# Don't allow access to .git folder
RedirectMatch 404 /\.git
```

#### Customise the Notification Message

You can customise the notification message using language overrides of the following two language strings
```ini
PLG_SYSTEM_GITDEPLOY_MESSAGE_BODY="<p>The Github user <a href='https://github.com/{pusherName}' title='@{pusherName}' target='_blank' rel='noopener noreferrer'>@{pusherName}</a> has pushed to <a href='{repoUrl}' title='{repoUrl}' target='_blank' rel='noopener noreferrer'>{repoUrl}</a> that changes have now been pulled into: <a href='{currentSite}' title='{currentSite}' target='_blank' rel='noopener noreferrer'>{currentSite}</a>.</p><p>Here's a brief list of what has been changed:</p>{commitsHtml}<p>What follows is the output of the script:</p><pre>{gitOutput}</pre><p>Kind Regards, <br>Github Webhook Endpoint</p>"
PLG_SYSTEM_GITDEPLOY_MESSAGE_BODY_COMMITS_LINE="<li>{commitMessage} (<small>Added: <strong>{commitAdded}</strong> Modified: <strong>{commitModified}</strong> Removed: <strong>{commitRemoved}</strong> Commit: <a href='{commitUrl}' title='{commitUrl}' target='_blank' rel='noopener noreferrer'>{commitUrl}</a></small>)</li>"
```

Please make sure that you only use the html `a`, `p`, `ul`, `li`, `strong`, `small`, `br` & `pre` tags as well as make sure the `title` attribute for links is the same as the infomation showed.

The following parameters are supported right now for the `PLG_SYSTEM_GITDEPLOY_MESSAGE_BODY` language string:

* `{pusherName}`: The github username that pushed the changes
* `{repoUrl}`: The URL to the github.com repo
* `{currentSite}`: The current site the changes get deployed to or the valud of the targetSite when configured
* `{commitsHtml}`: The generated commit lines
* `{gitOutput}`: The output of the git command we executed.

The following parameters are supported right now for the `PLG_SYSTEM_GITDEPLOY_MESSAGE_BODY_COMMITS_LINE` language string:

* `{commitMessage}`: The commit message for that commit
* `{commitAdded}`: The count of added lines
* `{commitModified}`: The count of modified lines
* `{commitRemoved}`: The count of removed lines
* `{commitUrl}`: The URL to the commit on github.com

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

<?php
/**
 * GitDeploy Plugin
 *
 * @copyright  Copyright (C) 2020 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

/**
 * Plugin class for GitDeploy
 *
 * @since  1.0
 */
class plgSystemGitDeploy extends CMSPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Application object.
	 *
	 * @var    CMSApplication
	 * @since  1.0
	 */
	protected $app;

	/**
	 * Raw post data
	 *
	 * @var    string
	 * @since  1.0
	 */
	private $rawPost;

	/**
	 * Listener for the `onAfterRoute` event
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  ErrorException
	 */
	public function onAfterRoute()
	{
		if ($this->app->input->getCmd('github', false))
		{
			set_error_handler(
				function($severity, $message, $file, $line)
				{
					throw new \ErrorException($message, 0, $severity, $file, $line);
				}
			);

			set_exception_handler(
				function($e)
				{
					header('HTTP/1.1 500 Internal Server Error');
					echo "Error on line {$e->getLine()}: " . htmlspecialchars($e->getMessage());
					$this->app->close();
				}
			);

			$hookSecret = $this->params->get('hookSecret', '');

			if ($this->params->get('checkHookSecret', 1) && !empty($hookSecret))
			{
				$this->checkSecret($hookSecret);
			}

			$this->checkContentType();
			$this->setPayload();
			$this->handleGitHubEvent();

			$this->app->close();
		}
	}

	/**
	 * Method to check the secret
	 *
	 * @param   array  $hookSecret  The seecret to check
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  Exception
	 */
	protected function checkSecret($hookSecret)
	{
		if (!$this->app->input->server->get('HTTP_X_HUB_SIGNATURE_256', false))
		{
			throw new \Exception("HTTP header 'X-Hub-Signature' is missing.");
		}

		if (!extension_loaded('hash'))
		{
			throw new \Exception("Missing 'hash' extension to check the secret code validity.");
		}

		list($algo, $hash) = explode('=', $this->app->input->server->getString('HTTP_X_HUB_SIGNATURE_256'), 2) + array('', '');

		if (!in_array($algo, hash_algos(), TRUE))
		{
			throw new \Exception("Hash algorithm '$algo' is not supported.");
		}

		$this->rawPost = file_get_contents('php://input');

		if ($hash !== hash_hmac($algo, $this->rawPost, $hookSecret))
		{
			throw new \Exception('Hook secret does not match.');
		}
	}

	/**
	 * Method to check the contentype
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  Exception
	 */
	protected function checkContentType()
	{
		if (!$this->app->input->server->getString('CONTENT_TYPE', false))
		{
			throw new \Exception("Missing HTTP 'Content-Type' header.");
		}
		elseif (!$this->app->input->server->getString('HTTP_X_GITHUB_EVENT', false))
		{
			throw new \Exception("Missing HTTP 'X-Github-Event' header.");
		}
	}

	/**
	 * Method to check the contentype
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  Exception
	 */
	protected function setPayload()
	{
		switch ($this->app->input->server->getString('CONTENT_TYPE'))
		{
			case 'application/json':
				$json = $this->rawPost ?: file_get_contents('php://input');
				break;

			case 'application/x-www-form-urlencoded':
				$json = $this->app->input->post->get('payload');
				break;

			default:
				throw new \Exception('Unsupported content type: ' . $this->app->input->server->getString('HTTP_CONTENT_TYPE'));
		}

		$this->payload = json_decode($json);
	}

	/**
	 * Method to handle the GitHub event
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  Exception
	 */
	protected function handleGitHubEvent()
	{
		$githubEvent = $this->app->input->server->get('HTTP_X_GITHUB_EVENT');

		switch (strtolower($githubEvent))
		{
			case 'ping':
				$this->sendNotificationMessage('Github Ping: <pre>' . print_r($this->payload) . '</pre>');
				break;

			case 'push':
				try
				{
					$this->runGitPull($this->payload);
				}
				catch (Exception $e)
				{
					$this->sendNotificationMessage($e->getMessage());
				}
				break;

			default:
				header('HTTP/1.0 404 Not Found');
				echo 'Event: ' . $githubEvent . ' Payload: \n' . $this->payload;
				$this->app->close();
		}
	}

	/**
	 * Method to rund the git pull command
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  Exception
	 */
	protected function runGitPull($payload)
	{
		$cd       = (bool) $this->params->get('cd', false);
		$cdPath   = (string) $this->params->get('cdPath', '');
		$gitReset = (bool) $this->params->get('gitReset', false);
		$git      = (string) $this->params->get('git', '');
		$repo     = (string) $this->params->get('repo', '');
		$branch   = (string) $this->params->get('branch', '');
		$remote   = (string) $this->params->get('remote', '');

		if ($payload->repository->url === 'https://github.com/' . $repo
			&& $payload->ref === 'refs/heads/' . $branch)
		{
			$finalCommand = '';

			if ($cd === true && is_dir($cdPath))
			{
				$finalCommand .= 'cd ' . $cdPath . ' && ';
			}

			if ($gitReset)
			{
				$finalCommand .= 'git reset --hard HEAD && ';
			}

			// Build the final command
			$finalCommand .= $git . ' pull ' . $remote . ' ' . $branch . ' 2>&1';

			// Execute the final command
			$output = shell_exec($finalCommand);

			// prepare and send the notification email
			if ($this->params->get('sendNotifications', 0))
			{
				$commitsHtml = '<ul>';

				foreach ($payload->commits as $commit)
				{
					$commitsHtmlLine = Text::_('PLG_SYSTEM_GITDEPLOY_MESSAGE_BODY_COMMITS_LINE');

					// Replace the variables
					$commitsHtmlLine = str_replace('{commitMessage}', $commit->message, $commitsHtmlLine);
					$commitsHtmlLine = str_replace('{commitAdded}', count($commit->added), $commitsHtmlLine);
					$commitsHtmlLine = str_replace('{commitModified}', count($commit->modified), $commitsHtmlLine);
					$commitsHtmlLine = str_replace('{commitRemoved}', count($commit->removed), $commitsHtmlLine);
					$commitsHtmlLine = str_replace('{commitUrl}', $commit->url, $commitsHtmlLine);

					$commitsHtml .= $commitsHtmlLine;
				}

				$commitsHtml .= '</ul>';

				$messageData['pusherName'] = $payload->pusher->name;
				$messageData['repoUrl'] = $payload->repository->url;
				$messageData['currentSite'] = Uri::base();
				$messageData['commitsHtml'] = $commitsHtml;
				$messageData['gitOutput'] = nl2br($output);

				$this->sendNotificationMessage(Text::_('PLG_SYSTEM_GITDEPLOY_MESSAGE_BODY'), $messageData);
			}

			return true;
		}
	}

	/**
	 * Send the Notifications to the configured notification providers
	 *
	 * @param   string  $message      The message to be sended out
	 * @param   array   $messageData  The array of messagedata to be replaced
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function sendNotificationMessage($message, $messageData = [])
	{
		foreach ($messageData as $key => $value)
		{
			$message = str_replace('{' . $key . '}', $value, $message);
		}

		$http = HttpFactory::getHttp();
		$notificationProvider = $this->params->get('notificationProvider', []);

		foreach ($notificationProvider as $provider)
		{
			if ($provider === 'glip')
			{
				if (isset($messageData['currentSite']))
				{
					$data['activity'] = 'GitDeploy for '. $messageData['currentSite'];
				}

				$data['body'] = $this->convertHtmlToMarkdownGlip($message);
				$data['title'] = 'Github Webhook Endpoint';

				$http->post($this->params->get('glipWebhook'), $data);
			}
			if ($provider === 'slack')
			{
				$data = [
					'payload' => json_encode(
						[
							'username' => $this->params->get('slackUsername'),
							'text'     => $message,
						]
					)
				];

				$http->post($this->params->get('slackWebhook'), $data);
			}

			if ($provider === 'mattermost')
			{
				$data = [
					'payload' => json_encode(
						[
							'text' => $message,
						]
					)
				];

				$http->post($this->params->get('mattermostWebhook'), $data);
			}

			if ($provider === 'telegram')
			{
				$data = [
					'chat_id'                  => $this->params->get('telegramChatId'),
					'parse_mode'               => 'MarkdownV2',
					'disable_web_page_preview' => 'true',
					'text'                     => $this->convertHtmlToMarkdownTelegram($message),
				];

				$http->post('https://api.telegram.org/bot' . $this->params->get('telegramBotToken') . '/sendMessage', $data);
			}

			if ($provider === 'email')
			{
				$mailer = Factory::getMailer();
				$config = Factory::getConfig();

				$recipient = $this->params->get('recipient', false);

				// This can only work when we have a recipient.
				if (!$recipient)
				{
					continue;
				}

				$subject = 'GitDeploy for your site';

				if (isset($messageData['currentSite']))
				{
					$subject = 'GitDeploy for '. $messageData['currentSite'];
				}

				$replayToEmail = $config->get('mailfrom');

				if (!empty($config->get('replyto')))
				{
					$replayToEmail = $config->get('replyto');
				}

				$mailer->addReplyTo($replayToEmail, $config->get('fromname'));
				$mailer->addRecipient($recipient);
				$mailer->isHtml(true);
				$mailer->setBody($message);
				$mailer->setSubject($subject);
				$send = $mailer->Send();
			}
		}
	}

	/**
	 * Converts the following tags from html to markdown: a, p, ul, li, strong, small, br, pre
	 *
	 * @param   string  $message      The message to be sended out
	 * @param   array   $messageData  The array of messagedata to be replaced
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function convertHtmlToMarkdownGlip($htmlContent)
	{
		// Strip all tags other than the supported tags
		$markdown = strip_tags($htmlContent, '<a><p><ul><li><strong><small><br><pre>');

		// Replace sequences of invisible characters with spaces
		$markdown = preg_replace('~\s+~u', ' ', $markdown);
		$markdown = preg_replace('~^#~u', '\\\\#', $markdown);

		// a
		$markdown = str_replace(" target='_blank' rel='noopener noreferrer'", '', $markdown);

		// https://stackoverflow.com/questions/18563753/getting-all-attributes-from-an-a-html-tag-with-regex
		preg_match_all('/<a(?:\s+(?:href=["\'](?P<href>[^"\'<>]+)["\']|title=["\'](?P<title>[^"\'<>]+)["\']|\w+=["\'][^"\'<>]+["\']))+/i', $markdown, $urlsMatch);
		$urls = array_unique($urlsMatch['href']);

		foreach ($urls as $id => $url)
		{
			$title = $urlsMatch['title'][$id];
			$markdownLink = '[' . $title .'](' . $url . ')';
			$markdown = str_replace("<a href='" . $url . "' title='" . $title . "'", $markdownLink, $markdown);
			$markdown = str_replace('>' . $title . '</a>', '', $markdown);
		}

		// Tag: p
		$markdown = str_replace('<p>', '', $markdown);
		$markdown = str_replace('</p>', PHP_EOL, $markdown);

		// Tag: ul
		$markdown = str_replace('<ul>', '', $markdown);
		$markdown = str_replace('</ul>', '', $markdown);

		// Tag: li
		$markdown = str_replace('<li>', '- ', $markdown);
		$markdown = str_replace('</li>', PHP_EOL, $markdown);

		// Tag: strong
		$markdown = str_replace('<strong>', '**', $markdown);
		$markdown = str_replace('</strong>', '**', $markdown);

		// Tag: small
		$markdown = str_replace('<small>', '', $markdown);
		$markdown = str_replace('</small>', '', $markdown);

		// Tag: br
		$markdown = str_replace('<br>', PHP_EOL, $markdown);
		$markdown = str_replace('<br/>', PHP_EOL, $markdown);
		$markdown = str_replace('<br />', PHP_EOL, $markdown);

		// Tag: pre
		$markdown = str_replace('<pre>', PHP_EOL, $markdown);
		$markdown = str_replace('</pre>', PHP_EOL, $markdown);

		// Remove leftover \n at the beginning of the line
		$markdown = ltrim($markdown, "\n");

		return $markdown;
	}

	/**
	 * Converts the following tags from html to markdown: a, p, ul, li, strong, small, br, pre
	 *
	 * @param   string  $message      The message to be sended out
	 * @param   array   $messageData  The array of messagedata to be replaced
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function convertHtmlToMarkdownTelegram($htmlContent)
	{
		// Strip all tags other than the supported tags
		$markdown = strip_tags($htmlContent, '<a><p><ul><li><strong><small><br><pre>');

		// Replace sequences of invisible characters with spaces
		$markdown = preg_replace('~\s+~u', ' ', $markdown);
		$markdown = preg_replace('~^#~u', '\\\\#', $markdown);
		$markdown = str_replace('(', '\\(', $markdown);
		$markdown = str_replace(')', '\\)', $markdown);
		$markdown = str_replace('[', '\\[', $markdown);
		$markdown = str_replace(']', '\\]', $markdown);
		$markdown = str_replace('-', '\\-', $markdown);

		// a
		$markdown = str_replace(" target='_blank' rel='noopener noreferrer'", '', $markdown);

		// https://stackoverflow.com/questions/18563753/getting-all-attributes-from-an-a-html-tag-with-regex
		preg_match_all('/<a(?:\s+(?:href=["\'](?P<href>[^"\'<>]+)["\']|title=["\'](?P<title>[^"\'<>]+)["\']|\w+=["\'][^"\'<>]+["\']))+/i', $markdown, $urlsMatch);
		$urls = array_unique($urlsMatch['href']);

		foreach ($urls as $id => $url)
		{
			$title = $urlsMatch['title'][$id];
			$markdownLink = '[' . $title .'](' . $url . ')';
			$markdown = str_replace("<a href='" . $url . "' title='" . $title . "'", $markdownLink, $markdown);
			$markdown = str_replace('>' . $title . '</a>', '', $markdown);
		}

		// Tag: p
		$markdown = str_replace('<p>', '', $markdown);
		$markdown = str_replace('</p>', PHP_EOL, $markdown);

		// Tag: ul
		$markdown = str_replace('<ul>', '', $markdown);
		$markdown = str_replace('</ul>', '', $markdown);

		// Tag: li
		$markdown = str_replace('<li>', '\\- ', $markdown);
		$markdown = str_replace('</li>', PHP_EOL, $markdown);

		// Tag: strong
		$markdown = str_replace('<strong>', '**', $markdown);
		$markdown = str_replace('</strong>', '**', $markdown);

		// Tag: small
		$markdown = str_replace('<small>', '', $markdown);
		$markdown = str_replace('</small>', '', $markdown);

		// Tag: br
		$markdown = str_replace('<br>', PHP_EOL, $markdown);
		$markdown = str_replace('<br/>', PHP_EOL, $markdown);
		$markdown = str_replace('<br />', PHP_EOL, $markdown);

		// Tag: pre
		$markdown = str_replace('<pre>', PHP_EOL, $markdown);
		$markdown = str_replace('</pre>', PHP_EOL, $markdown);

		// Remove leftover \n at the beginning of the line
		$markdown = ltrim($markdown, "\n");

		$markdown = str_replace('.', '\\.', $markdown);
		$markdown = str_replace('_', '\\_', $markdown);
		$markdown = str_replace('>', '\\>', $markdown);
		$markdown = str_replace('<', '\\<', $markdown);
		$markdown = str_replace(' * ', ' \\* ', $markdown);

		return $markdown;
	}
}

<?php
/*
    LMStopForumSpam Nucleus plugin
    Copyright (C) 2013-2014 Leo (http://nucleus.slightlysome.net/leo)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
	(http://www.gnu.org/licenses/gpl-2.0.html)
	
	See lmstopforumspam/help.html for plugin description, install, usage and change history.
*/
class NP_LMStopForumSpam extends NucleusPlugin
{
	// name of plugin 
	function getName()
	{
		return 'LMStopForumSpam';
	}

	// author of plugin
	function getAuthor()
	{
		return 'Leo (http://nucleus.slightlysome.net/leo)';
	}

	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL()
	{
		return 'http://nucleus.slightlysome.net/plugins/lmstopforumspam';
	}

	// version of the plugin
	function getVersion()
	{
		return '1.0.0';
	}

	// a description to be shown on the installed plugins listing
	function getDescription()
	{
		return 'Spam check plugin for the LMCommentModerator plugin that uses the http://www.stopforumspam.com/ API to check comments';
	}

	function supportsFeature ($what)
	{
		switch ($what)
		{
			case 'SqlTablePrefix':
				return 1;
			case 'SqlApi':
				return 1;
			case 'HelpPage':
				return 1;
			default:
				return 0;
		}
	}
	
	function hasAdminArea()
	{
		return 1;
	}
	
	function getMinNucleusVersion()
	{
		return '360';
	}
	
	function getTableList()
	{	
		return 	array();
	}
	
	function getEventList() 
	{ 
		return array('AdminPrePageFoot', 'LMCommentModerator_SpamCheck'); 
	}
	
	function getPluginDep() 
	{
		return array('NP_LMCommentModerator');
	}

	function install()
	{
		$sourcedataversion = $this->getDataVersion();

		$this->upgradeDataPerform(1, $sourcedataversion);
		$this->setCurrentDataVersion($sourcedataversion);
		$this->upgradeDataCommit(1, $sourcedataversion);
		$this->setCommitDataVersion($sourcedataversion);					
	}
	
	function event_AdminPrePageFoot(&$data)
	{
		// Workaround for missing event: AdminPluginNotification
		$data['notifications'] = array();
			
		$this->event_AdminPluginNotification($data);
			
		foreach($data['notifications'] as $aNotification)
		{
			echo '<h2>Notification from plugin: '.htmlspecialchars($aNotification['plugin'], ENT_QUOTES, _CHARSET).'</h2>';
			echo $aNotification['text'];
		}
	}
	
	////////////////////////////////////////////////////////////
	//  Events
	function event_AdminPluginNotification(&$data)
	{
		global $member, $manager;
		
		$actions = array('overview', 'pluginlist', 'plugin_LMStopForumSpam');
		$text = "";
		
		if(in_array($data['action'], $actions))
		{
			$sourcedataversion = $this->getDataVersion();
			$commitdataversion = $this->getCommitDataVersion();
			$currentdataversion = $this->getCurrentDataVersion();
		
			if($currentdataversion > $sourcedataversion)
			{
				$text .= '<p>An old version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin files are installed. Downgrade of the plugin data is not supported. The correct version of the plugin files must be installed for the plugin to work properly.</p>';
			}
			
			if($currentdataversion < $sourcedataversion)
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is for an older version of the plugin than the version installed. ';
				$text .= 'The plugin data needs to be upgraded or the source files needs to be replaced with the source files for the old version before the plugin can be used. ';

				if($member->isAdmin())
				{
					$text .= 'Plugin data upgrade can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.';
				}
				
				$text .= '</p>';
			}
			
			if($commitdataversion < $currentdataversion && $member->isAdmin())
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is upgraded, but the upgrade needs to commited or rolled back to finish the upgrade process. ';
				$text .= 'Plugin data upgrade commit and rollback can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.</p>';
			}
		}
		
		if($text)
		{
			array_push(
				$data['notifications'],
				array(
					'plugin' => $this->getName(),
					'text' => $text
				)
			);
		}
	}

	function event_LMCommentModerator_SpamCheck(&$data)
	{
		$spamcheck = $data['spamcheck'];

		if(!$spamcheck['result'] && $this->getOption('spamcheckenabled') == 'yes')
		{
			$result = false;
			$message = false;
			$ip = false;
			$email = false;
			$username = false;

			$sfsparams = array();
			
			if($this->getOption('spamcheckip') == 'yes')
			{
				$ip = $spamcheck['ip'];
				
				if($ip)
				{
					$sfsparams['ip'] = $ip;
				}
			}
			
			if($this->getOption('spamcheckemail') == 'yes')
			{
				$email = $spamcheck['email'];
				
				if($email)
				{
					$sfsparams['email'] = $email;
				}
			}
			
			if($this->getOption('spamcheckname') == 'yes' && $email)
			{
				$username = $spamcheck['author'];

				if((strtoupper(_CHARSET) != 'ISO-8859-1') && (strtoupper(_CHARSET) != 'UTF-8') && $username)
				{
					$username = iconv(_CHARSET, 'UTF-8', $username);
				}
				
				if($username)
				{
					$sfsparams['username'] = $username;
				}
			}

			if($sfsparams)
			{
				$appears = array();
				$url = 'http://www.stopforumspam.com/api?'.http_build_query($sfsparams, '', '&').'&f=json';
				
		        $json = file_get_contents($url);
				
				if($json !== false)
				{
					$sfsresult = json_decode($json, true);
				
					if($sfsresult != null)
					{
						if($sfsresult['success'])
						{
							if($ip)
							{
								if($sfsresult['ip']['appears'])
								{
									$appears['ip'] = 'IP listed on www.stopforumspam.com (confidence: '.$sfsresult['ip']['confidence'].')';
								}
							}

							if($email)
							{
								if($sfsresult['email']['appears'])
								{
									$appears['email'] = 'EMail listed on www.stopforumspam.com (confidence: '.$sfsresult['email']['confidence'].')';
								}
							}

							if($username)
							{
								if($sfsresult['username']['appears'])
								{
									$appears['username'] = 'Username listed on www.stopforumspam.com (confidence: '.$sfsresult['username']['confidence'].')';
								}
							}
							
							if($appears)
							{
								$result = 'S';
								$message = implode(', ', $appears);
							}
						}
						else
						{
					        ACTIONLOG::add(ERROR, $this->getName().': API call returned failure');
						}
					}
					else
					{
				        ACTIONLOG::add(ERROR, $this->getName().': Failed to decode json object');
					}
				}
				else
				{
			        ACTIONLOG::add(ERROR, $this->getName().': url open failed');
				}
			}

			if($result)
			{
				$spamcheck['result'] = $result;
				$spamcheck['message'] = $message;
				$spamcheck['plugin'] = $this->getName();
			}
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	// Plugin Upgrade handling functions
	function getCurrentDataVersion()
	{
		$currentdataversion = $this->getOption('currentdataversion');
		
		if(!$currentdataversion)
		{
			$currentdataversion = 0;
		}
		
		return $currentdataversion;
	}

	function setCurrentDataVersion($currentdataversion)
	{
		$res = $this->setOption('currentdataversion', $currentdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getCommitDataVersion()
	{
		$commitdataversion = $this->getOption('commitdataversion');
		
		if(!$commitdataversion)
		{
			$commitdataversion = 0;
		}

		return $commitdataversion;
	}

	function setCommitDataVersion($commitdataversion)
	{	
		$res = $this->setOption('commitdataversion', $commitdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getDataVersion()
	{
		return 1;
	}
	
	function upgradeDataTest($fromdataversion, $todataversion)
	{
		// returns true if rollback will be possible after upgrade
		$res = true;
				
		return $res;
	}
	
	function upgradeDataPerform($fromdataversion, $todataversion)
	{
		// Returns true if upgrade was successfull
		
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$this->createOption('currentdataversion', 'currentdataversion', 'text','0', 'access=hidden');
					$this->createOption('commitdataversion', 'commitdataversion', 'text','0', 'access=hidden');

					$this->createOption('spamcheckip', 'Check IP against StopForumSpam?', 'yesno','yes');
					$this->createOption('spamcheckemail', 'Check EMail against StopForumSpam?', 'yesno','yes');
					$this->createOption('spamcheckname', 'Check Name against StopForumSpam?', 'yesno','no');

					$this->createOption('spamcheckenabled', 'Enable spam check against StopForumSpam?', 'yesno','yes');

					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		
		return true;
	}
	
	function upgradeDataRollback($fromdataversion, $todataversion)
	{
		// Returns true if rollback was successfull
		for($ver = $fromdataversion; $ver >= $todataversion; $ver--)
		{
			switch($ver)
			{
				case 1:
					$res = true;
					break;
				
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function upgradeDataCommit($fromdataversion, $todataversion)
	{
		// Returns true if commit was successfull
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		return true;
	}
}
?>

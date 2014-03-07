<?php

	class github_wiki extends SlackServicePlugin {

		public $name = "Github Wiki";
		public $desc = "Source control and code management.";

		public $cfg = array(
			'has_token'	=> true,
		);

		function onInit(){

			$channels = $this->getChannelsList();
			array_merge($channels,$this->getGroupsList());
			foreach ($channels as $k => $v){
				if ($v == '#general'){
					$this->icfg['channel'] = $k;
					$this->icfg['channel_name'] = $v;
				}
			}

			$this->icfg['branch']	= '';
			$this->icfg['botname']	= 'github';
			$this->icfg['icon_url']     = trim($GLOBALS['cfg']['root_url'], '/') . '/plugins/github_wiki/icon_48.png';
			// $this->icfg['icon_url'] = "https://slack.global.ssl.fastly.net/10562/img/services/github_48.png";
		}

		function onView(){

			return $this->smarty->fetch('view.txt');
		}

		function onEdit(){

			$channels = $this->getChannelsList();
			array_merge($channels,$this->getGroupsList());

			if ($_GET['save']){
				$this->icfg['channel'] = $_POST['channel'];
				$this->icfg['channel_name'] = $channels[$_POST['channel']];
				$this->icfg['branch'] = $_POST['branch'];
				$this->icfg['botname'] = $_POST['botname'];
				$this->saveConfig();

				header("location: {$this->getViewUrl()}&saved=1");
				exit;
			}

			$this->smarty->assign('channels', $channels);

			return $this->smarty->fetch('edit.txt');
		}

		function onHook($req){

			if (!$this->icfg['channel']){
				return array(
					'ok'	=> false,
					'error'	=> "No channel configured",
				);
			}

			$github_payload = json_decode($req['post']['payload'], true);

			if (!$github_payload || !is_array($github_payload)){
				return array(
					'ok'	=> false,
					'error' => "No payload received from github",
				);
			}

			#
			# wiki event
			#
			$wiki_count = count($github_payload['pages']);
			if ($wiki_count >= 1){

				$username = $github_payload['sender']['login'];
				$text = '';
				$i = 0;
				foreach ($github_payload['pages'] as $wiki){
					$text .= $this->escapeLink($github_payload['sender']['html_url'], $username);
					$text .= $this->escapeText(" {$wiki[action]} the ");
					$text .= $this->escapeLink($wiki['html_url'],$wiki['title']);
					$text .= $this->escapeText(" wiki page of ");
					$text .= $this->escapeLink($wiki['html_url'], $github_payload['repository']['full_name']);

					$i++;
					if ($i == 10 && ($wiki_count-$i)>1) break;
				}
				if ($i != $wiki_count){
					$text .= "\nAnd ".$this->escapeLink($github_payload['compare'], ($commit_count-$i)." others");
				}
				return $this->sendMessage($text);
			}

			return array(
				'ok'		=> true,
				'status'	=> "Nothing found to report",
			);
		}

		function getLabel(){
			return "Post wiki update to {$this->icfg['channel_name']} as {$this->icfg['botname']}";
		}

		private function sendMessage($text){

			$ret = $this->postToChannel($text, array(
                                'channel'       => $this->icfg['channel'],
                                'username'      => $this->icfg['botname'],
                                'icon_url' => $this->icfg['icon_url'],
                        ));

			return array(
				'ok'		=> true,
				'status'	=> "Sent a message",
			);
		}
	}

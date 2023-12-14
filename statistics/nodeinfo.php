<?php

function nodeinfo_content() {

	// Nodeinfo: what a stinking piece of crap.

	// We have to lie and say we're redmatrix because the schema was defined a bit too rigidly

	$hidden = get_config('diaspora','hide_in_statistics');
	$arr = [];
	if($hidden) {
		if(argc() > 1 && in_array(argv(1), ['2.0', '2.1'])) {
			$arr = [
				'version' => '2.0',
				'software' => [
					'name' => strtolower(Zotlabs\Lib\System::get_platform_name()),
					'version' => Zotlabs\Lib\System::get_project_version()
				],
				'protocols' => [ 'zot' ],
				'services' => [],
				'openRegistrations' => false,
				'usage' => [ 'users' => [ 'total' => 1, 'activeHalfyear' => 1, 'activeMonth' => 1 ],
					'localPosts' => 1,
					'localComments' => 1
				],
				'metadata' => [ 'nodeName' => get_config('system','sitename') ]
			];

			if(argv(1) === '2.1') {
				$arr['version'] = '2.1';
				$arr['software']['repository'] = 'https://framagit.org/hubzilla/core';
				$arr['software']['homepage'] = 'https://hubzilla.org';
			}
		}
	}

	elseif(argc() > 1 && in_array(argv(1), ['2.0', '2.1'])) {
		$arr = [
			'version'           => '2.0',
			'software'          =>  [
				'name' => strtolower(Zotlabs\Lib\System::get_platform_name()),
				'version' => Zotlabs\Lib\System::get_project_version()
			],
			'protocols'         => [ 'zot' ],
			'services'          => [],
			'openRegistrations' => ((intval(get_config('system','register_policy')) === REGISTER_OPEN) ? true : false),

			'usage' => [
				'users' => [
					'total' => intval(get_config('system','channels_total_stat')),
					'activeHalfyear' => intval(get_config('system','channels_active_halfyear_stat')),
					'activeMonth' => intval(get_config('system','channels_active_monthly_stat')),
				],
				'localPosts' => intval(get_config('system','local_posts_stat')),
				'localComments' => intval(get_config('system','local_comments_stat')),
			],
			'metadata' => [ 'nodeName' => get_config('system','sitename') ]
		];

		if(argv(1) === '2.1') {
			$arr['version'] = '2.1';
			$arr['software']['repository'] = 'https://framagit.org/hubzilla/core';
			$arr['software']['homepage'] = 'https://hubzilla.org';
		}

		if(in_array('diaspora',App::$plugins)) {
			$arr['protocols'][] = 'diaspora';
		}

		if(in_array('pubcrawl',App::$plugins)) {
			$arr['protocols'][] = 'activitypub';
		}

		$services = [ 'atom1.0' ];
		$iservices = [ 'atom1.0', 'rss2.0' ];

		if(in_array('diaspost',App::$plugins))
			$services[] = 'diaspora';
		if(in_array('dwpost',App::$plugins))
			$services[] = 'dreamwidth';
		if(in_array('statusnet',App::$plugins))
			$services[] = 'gnusocial';
		if(in_array('rtof',App::$plugins))
			$services[] = 'friendica';
		if(in_array('gpluspost',App::$plugins))
			$services[] = 'google';
		if(in_array('ijpost',App::$plugins))
			$services[] = 'insanejournal';
		if(in_array('libertree',App::$plugins))
			$services[] = 'libertree';
		if(in_array('pumpio',App::$plugins))
			$services[] = 'pumpio';
		if(in_array('redred',App::$plugins))
			$services[] = 'redmatrix';
		if(in_array('twitter',App::$plugins))
			$services[] = 'twitter';
		if(in_array('wppost',App::$plugins)) {
			$services[] = 'wordpress';

		}
		if(in_array('xmpp',App::$plugins)) {
			$services[] = 'xmpp';
		}

		if($services)
			$arr['services']['outbound'] = $services;
		if($iservices)
			$arr['services']['inbound'] = $iservices;
	}

	json_return_and_die($arr);

}


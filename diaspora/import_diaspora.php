<?php

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Connect;
use Zotlabs\Lib\AccessList;
use Zotlabs\Daemon\Master;

require_once('include/markdown.php');
require_once('include/photo/photo_driver.php');

function import_diaspora_account($data) {

	$account = App::get_account();

	if (!$account) {
		notice( t('No account to import to.') . EOL);
		return false;
	}

	if ($data['version'] !== '2.0') {
		notice( t('Incompatible data version - aborting') . EOL);
		return false;
	}


	if (empty($data['user']['username'])) {
		notice( t('No username found in import file.') . EOL);
		return false;
	}

	$address = escape_tags($data['user']['username']);

	$r = q("select * from channel where channel_address = '%s' limit 1",
		dbesc($address)
	);

	if ($r) {
		// try at most ten times to generate a unique address.
		$x = 0;
		$found_unique = false;
		do {
			$tmp = $address . mt_rand(1000,9999);
			$r = q("select * from channel where channel_address = '%s' limit 1",
				dbesc($tmp)
			);
			if(! $r) {
				$address = $tmp;
				$found_unique = true;
				break;
			}
			$x ++;
		} while ($x < 10);

		if (!$found_unique) {
			logger('import_diaspora: duplicate channel address. randomisation failed.');
			notice( t('Unable to create a unique channel address. Import failed.') . EOL);
			return;
		}
	}

	$pr = $data['user']['profile']['entity_data'];

	$c = create_identity(array(
		'name' => escape_tags($pr['first_name'] . (($pr['last_name']) ? ' ' . $pr['last_name'] : '')),
		'nickname' => $address,
		'account_id' => $account['account_id'],
		'permissions_role' => 'public'
	));

	if (!$c['success']) {
		return;
	}

	$channel_id = $c['channel']['channel_id'];

	if (!Apps::addon_app_installed($channel_id, 'diaspora')) {
		Apps::app_install($channel_id, 'Diaspora Protocol');
	}

	if (!Apps::system_app_installed($channel_id, 'Privacy Groups')) {
		Apps::app_install($channel_id, 'Privacy Groups');
	}

	// todo - add auto follow settings, (and strip exif in hubzilla)

	if (!empty($pr['location'])) {
		$location = escape_tags($pr['location']);

		q("update channel set channel_location = '%s' where channel_id = %d",
			dbesc($location),
			intval($channel_id)
		);
	}

	if (!empty($pr['nsfw'])) {
		q("update channel set channel_pageflags = (channel_pageflags | %d) where channel_id = %d",
			intval(PAGE_ADULT),
			intval($channel_id)
		);
	}

	if (!empty($pr['image_url'])) {
		import_channel_photo_from_url($pr['image_url'], $account['account_id'], $channel_id);
	}

	$gender = '';
	if (!empty($pr['gender'])) {
		$gender = escape_tags($pr['gender']);
	}

	$about = markdown_to_bb($pr['bio'], false, [ 'diaspora' => true ]);

	$publish = intval($pr['searchable']);

	$dob = NULL_DATE;
	if (!empty($pr['birthday'])) {
		$dob = datetime_convert('UTC', 'UTC', $pr['birthday'], 'Y-m-d');
	}

	// we're relying on the fact that this channel was just created and will only
	// have the default profile currently

	$r = q("update profile set gender = '%s', about = '%s', dob = '%s', publish = %d where uid = %d",
		dbesc($gender),
		dbesc($about),
		dbesc($dob),
		dbesc($publish),
		intval($channel_id)
	);

	if($data['user']['contact_groups']) {
		foreach($data['user']['contact_groups'] as $aspect) {
			AccessList::add($channel_id, escape_tags($aspect['name']), intval($aspect['contacts_visible']));
		}
	}

	// now add connections and send friend requests

	// There is a possibility that the import will timeout if there are many contacts to import.
	// Move to backgound job?

	if($data['user']['contacts']) {
		foreach($data['user']['contacts'] as $contact) {
			$result = Connect::connect($c['channel'], $contact['account_id']);
			if($result['success']) {
				if($contact['contact_groups_membership']) {
					foreach($contact['contact_groups_membership'] as $aspect) {
						AccessList::member_add($channel_id, $aspect, $result['abook']['xchan_hash']);
					}
				}
			}
		}
	}

	// TODO: add items:

	// There is a possibility that the import will timeout if there are many posts to import.
	// Move to backgound job?

	// Another challenge is to map the acl for non pulic items.
	// The exported items only contain the subscribed contacts info (webbies) but nothing about the privacy group.
	// Should we map them to ourself only?

	// Also how do we deal with photos? They are provided in a separate export file.


	// This will indirectly perform a refresh_all *and* update the directory
	Master::Summon(['Directory', $channel_id]);

	notice(t('Import completed.') . EOL);

	change_channel($channel_id);

	goaway(z_root() . '/hq');

}

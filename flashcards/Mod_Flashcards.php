<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Storage\Directory;
use Zotlabs\Storage\File;
use Zotlabs\Storage\BasicAuth;

class Flashcards extends Controller {

    private $version = "25.05.27";
    private $boxesDir;
    private $is_owner;
    private $owner;
    private $observer;
    private $auth;
    private $lengthBoxId = 15;

    function init() {

        $actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        logger('addon flashcards is receiving a request.. ' . $actual_link, LOGGER_DEBUG);

        // Determine which channel's flashcards to display to the observer
        $nick = null;
        if (argc() > 1) {
            $nick = argv(1); // if the channel name is in the URL, use that
        }
        logger('nick = ' . $nick, LOGGER_DEBUG);
        $this->owner = channelx_by_nick($nick);

        $this->initAuth();

//        $this->flashcards_merge_test();
    }

    function get() {

        if (!$this->checkObserver()) {
            logger('Stop. Observer not allowed. Sending login...', LOGGER_DEBUG);
            return login();
        }

        if (!$this->owner) {
            if (local_channel()) { // if no channel name was provided, assume the current logged in channel
                $channel = App::get_channel();
                logger('No nick but local channel - channel = ' . $channel, LOGGER_DEBUG);
                if (isset($channel['channel_address'])) {
                    $nick = $channel['channel_address'];
                    goaway(z_root() . '/flashcards/' . $nick);
                }
            }

            logger('No nick and no local channel', LOGGER_DEBUG);
            notice(t('Profile Unavailable.') . EOL);
            goaway(z_root());
        }

        $which = null;
        if (argc() > 1) {
            $which = argv(1);
        }

        $profile = 0;

        if ($which) {
            profile_load($which, $profile);
        }

        if (!App::$profile) {
            notice(t('Requested profile is not available.') . EOL);
            return;
        }

        if (!Apps::addon_app_installed(App::$profile_uid, 'flashcards')) {
            //Do not display any associated widgets at this point
            //App::$pdl = EMPTY_STR;

            if (App::$profile_uid !== local_channel() || !$this->owner) {
                $this->sendAddonNotInstalled();
            }

            $papp = Apps::get_papp('Flashcards');
            return Apps::app_render($papp, 'module');
        }

        nav_set_selected('Flashcards');

        $status = $this->permChecks();
        $this->getAddonDir();

        if (!$status['status']) {
            logger('observer prohibited', LOGGER_DEBUG);
            notice($status['errormsg'] . EOL);
            goaway(z_root());
        }

        if (argc() > 2) {
            switch (argv(2)) {
                case 'contacts':
                    // API: /flashcards/nick/contacts
                    // get contacts, (connected and close enough)
                    $this->sendContacts();
                case 'list':
                    // API: /flashcards/nick/list
                    // List all boxes owned by the channel
                    // 
                    // This can be called by a contact server to server
                    $this->sendBoxes();
                case 'download':
                    // API: /flashcards/nick/download/boxid
                    // Downloads a box specified by "box_id"
                    $this->sendBox();
                case 'sync':
                    // API: /flashcards/nick/sync/boxID/affinity
                    // sync a box with boxes of contacts
                    // 
                    // This will download a box (as cloud file) of a contact server to server.
                    $this->syncBox();
                case 'delete':
                    // API: /flashcards/nick/delete/boxid
                    // Deletes a box specified by "box_id"
                    $this->deleteBox();
            }
        }

        if (!$this->observer) {
            logger('Stop. Observer not known. Sending login...', LOGGER_DEBUG);
            return login();
        }

        //----------------------------------------------------------------------
        // -- Start copy of Contactedit.php ------------------------------------

        $slide = 'none';

        if (Apps::system_app_installed(local_channel(), 'Affinity Tool')) {
            $slide = 'initial';
        }

        $slideval = intval("80");

        // -- End copy of Contactedit.php --------------------------------------
        //----------------------------------------------------------------------

        head_add_css('/addon/flashcards/view/css/flashcards.css');

        $o = replace_macros(get_markup_template('flashcards.tpl', 'addon/flashcards'), array(
            '$post_url' => 'flashcards/' . $this->owner['channel_address'],
            '$nick' => $this->owner['channel_address'],
            '$flashcards_editor' => $this->observer['xchan_addr'],
            '$flashcards_owner' => $this->owner['xchan_addr'],
            '$owner_xchan_addr' => $this->owner['xchan_addr'],
            '$owner_xchan_hash' => $this->owner['xchan_hash'],
            '$is_local_channel' => ((local_channel() && $this->observer) ? true : false),
            '$has_write_permission' => $this->is_owner, // only the logged in owner can create or change flashcards
            '$flashcards_version' => $this->version,
            '$affinity_label' => t('Affinity'), // copy of Contactedit.php
            '$val' => $slideval, // copy of Contactedit.php
            '$slide' => $slide // copy of Contactedit.php
        ));

        return $o;
    }

    function post() {

        if (!$this->checkObserver()) {
            logger('Stop. Observer not allowed. Sending login...', LOGGER_DEBUG);
            http_status_exit(403, 'Permission denied.');
        }

        if (!Apps::addon_app_installed($this->owner['channel_id'], 'flashcards')) {
            $this->sendAddonNotInstalled();
        }

        $status = $this->permChecks();
        $this->getAddonDir();

        if (!$status['status']) {
            notice($status['errormsg'] . EOL);
            json_return_and_die(array('status' => false, 'errormsg' => $status['errormsg'] . EOL));
        }

        if (argc() > 2) {
            switch (argv(2)) {
                case 'upload':
                    // API: /flashcards/nick/upload
                    // Creates or merges a box
                    $this->writeBox();
                case 'download':
                    // API: /flashcards/nick/download/boxid
                    // Downloads a box specified by "box_id"
                    $this->sendBox();
                case 'sync':
                    // API: /flashcards/nick/sync/boxID/affinity
                    // sync a box with boxes of contacts
                    // 
                    // This will download a box (as cloud file) of a contact server to server.
                    $this->syncBox();
                case 'list':
                    // API: /flashcards/nick/list
                    // List all boxes owned by the channel
                    // 
                    // This can be called by a contact server to server
                    $this->sendBoxes();
                case 'fetchboxes':
                    // API: /flashcards/nick/fetchboxes
                    // fetch the boxes of a contact
                    // 
                    // This will download the boxes of a contact server to server.
                    // It will call the api endpoint /flashcards/nick/list
                    $this->fetchBoxesFromContact();
                case 'import':
                    // API: /flashcards/nick/import
                    // fetch the boxes of a contact
                    $this->importBox();
                case 'delete':
                    // API: /flashcards/nick/delete/boxid
                    // Deletes a box specified by "box_id"
                    $this->deleteBox();
                case 'contacts':
                    // API: /flashcards/nick/contacts
                    // get contacts of owner
                    $this->sendContacts();
                default:
                    break;
            }
        }
    }

    private function sendAddonNotInstalled() {
        $contact["xchan_hash"] = $this->owner['xchan_hash'];
        $contact["xchan_name"] = $this->owner['xchan_name'];
        $errormsg = "Addon'flashcards' not installed for user " . $contact["xchan_name"] . " on this server.";
        logger('Stop. Sending message...  ' . $errormsg, LOGGER_DEBUG);
        json_return_and_die(array('status' => false, 'boxes' => array(), 'contact' => $contact, 'errormsg' => $errormsg));
    }

    private function permChecks() {

        $owner_uid = $this->owner['channel_id'];

        //logger('DELETE ME: This is the owner...', LOGGER_DEBUG);
        //logger(print_r($this->owner, true), LOGGER_DEBUG);

        if (!$owner_uid) {  // This IF should be checked before and could be deleted
            logger('Stop: No owner profil');
            return array('status' => false, 'errormsg' => 'No owner profil');
        }

        // This is the permission to view cloud file in general.
        // The folder that stores the addon files has extra permissions.
        if (!perm_is_allowed($owner_uid, get_observer_hash(), 'view_storage')) {
            logger('Stop: Permission view storage denied');
            return array('status' => false, 'errormsg' => 'Permission view storage denied');
        }

        logger('observer = ' . $this->observer['xchan_addr'] . ', owner = ' . $this->owner['xchan_addr'], LOGGER_DEBUG);

        // We could check the permission "write_storage" as it was in a former version.
        // But we say ONLY the logged in owner can add boxes/flashcards and change them.
        $this->is_owner = ($this->observer['xchan_hash'] && $this->observer['xchan_hash'] == $this->owner['xchan_hash']);
        if ($this->is_owner) {
            logger('observer = owner', LOGGER_DEBUG);
        } else {
            logger('observer != owner', LOGGER_DEBUG);
        }

        return array('status' => true);
    }

    private function checkObserver() {

        if (observer_prohibited(true)) {
            logger('Stop. Observer not allowed.', LOGGER_DEBUG);
            return false;
        }

        $this->observer = App::get_observer();
        //logger('This is the observer...', LOGGER_DEBUG);
        //logger(print_r($this->observer, true), LOGGER_DEBUG);
        return true;
    }

    private function sendBoxes() {

        logger('+++ list boxes ... +++', LOGGER_DEBUG);

        $ret = $this->readBoxes(true);

        logger('sending list of boxes...', LOGGER_DEBUG);

        $contact["xchan_hash"] = $this->owner['xchan_hash'];
        $contact["xchan_name"] = $this->owner['xchan_name'];
        $body["contact"] = $contact;
        $body["boxes"] = $ret["boxes"];

        json_return_and_die(array('status' => $ret["status"], 'body' => $body, 'errormsg' => $ret["errormsg"]));
    }

    private function importBox() {
        logger('+++ import box ... +++', LOGGER_DEBUG);

        $url = $_POST['url'];
        if (!$url) {
            logger('no url in body of post given', LOGGER_DEBUG);
            return (array('status' => false, 'errormsg' => "no url given"));
        }

        $x = $this->fetchRessource($url);

        if (!$x['success']) {
            $msg = "Return code " . $x["return_code"];
            logger($msg, LOGGER_DEBUG);
            return(array('status' => false, 'errormsg' => $msg));
        }

        try {
            $box = json_decode($x["body"], true);
        } catch (\Exception $e) {
            $msg = "Exception: " . $e->getMessage() . " when parsing body in response: " . $x["body"];
            logger($msg, LOGGER_DEBUG);
            return(array('status' => false, 'errormsg' => $msg));
        }

        $hash = random_string($this->lengthBoxId);

        $box["boxID"] = $hash;
        $box['lastShared'] = round(microtime(true) * 1000);

        $box = $this->clearBox($box);

        $filename = $hash . '.json';
        $this->boxesDir->createFile($filename, json_encode($box));

        logger('The box was written to ' . $filename, LOGGER_DEBUG);

        $u = $this->owner['xchan_url'];
        $redirect_url = str_replace("/channel/", "/flashcards/", $u) . "/" . $hash;
        logger("Import finished. Redirect URL is " . $redirect_url, LOGGER_DEBUG);

        json_return_and_die(
                array(
                    'status' => true,
                    'redirect_url' => $redirect_url));
    }

    private function syncBox() {
        logger('+++ synchronize box ... +++', LOGGER_DEBUG);

        if (!argv(3)) {
            logger("no box id found in url", LOGGER_DEBUG);
            json_return_and_die(array('status' => false, 'errormsg' => "no box id found in url"));
        }

        $boxID = argv(3);
        $affinity = argv(4) ? argv(4) : 80;

        $box = $this->readBox($this->boxesDir, $boxID . '.json');
        if (!$box) {
            logger('local box not found or failed to load: ' . $box["boxID"] . '.json', LOGGER_DEBUG);
            // this box might be delete but still present in the local storage of the web browser
            json_return_and_die(array('status' => false, 'upload' => true, 'errormsg' => 'local box not found or failed to load: ' . $box["boxID"] . '.json'));
        }

        if ($box["private_block"] === true || $box["private_block"] === "true") {
            // Double check. Should have been done in web browser and never sent to here.
            json_return_and_die(array('status' => false, 'errormsg' => 'this box does not want to be updated from contacts'));
        }

        $changed = false;

        $contacts = $this->getContacts();

        foreach ($contacts as $c) {

            if ($this->owner["xchan_hash"] === $c["xchan_hash"]) {
                continue;
            }

            if ($affinity < $c["affinity"]) {
                logger("Ignoring contact " . $c['xchan_addr'] . " with affinity " . $c["affinity"], LOGGER_DEBUG);
                continue;
            }

            $x = $this->fetchRessource($c["url_list_boxes"]);

            $ret = $this->readBoxFromResponse($x);

            if (!$ret['status']) {
                $msg = 'Failed to fetch url ' . $url . " . message " . $ret["errormsg"];
                logger($msg, LOGGER_DEBUG);
                continue;
            }

            if (sizeof($ret["boxes"]) === 0) {
                continue;
            }

            foreach ($ret["boxes"] as $box_contact) {
                if ($box_contact["boxPublicID"] !== $box["boxPublicID"]) {
                    continue;
                }

                // Download the box as json file
                $cloudFile = $c["url_cloud_box"] . "/" . $box_contact["boxID"] . ".json";

                $r = $this->fetchRessource($cloudFile);

                if (!$r['success']) {
                    $msg = "Return code " . $r["return_code"] . " when requesting file " . $cloudFile;
                    logger($msg, LOGGER_DEBUG);
                }

                try {
                    $box_contact = json_decode($r["body"], true);
                } catch (\Exception $e) {
                    $msg = "Exception: " . $e->getMessage() . " after downloading " . $cloudFile . " when parsing body of response: " . $r["body"];
                    logger($msg, LOGGER_DEBUG);
                    // return(array('status' => false, 'errormsg' => $msg));
                    // In rare case a user might choose to have more than one
                    // box with the same publicID (copies) by setting the
                    // boxID manually. How knows. In this case "continue" would
                    // catch those boxes too. (So it is not just looping to the end here.)
                    continue;
                }

                // Merge the changes from the contact
                $boxes = $this->flashcards_merge($box, $box_contact, false);
                $boxToWrite = $boxes['boxLocal'];
                if ($boxes["changed"]) {
                    $changed = true;
                    $boxToWrite['lastShared'] = $boxToSend['lastShared'] = round(microtime(true) * 1000);

                    try {
                        $this->boxesDir->getChild($box["boxID"] . '.json')->put(json_encode($boxToWrite));
                    } catch (\Exception $e) {
                        // Keep this in mind also: The observer might not be the owner and
                        // - might have write permissions, or
                        // - might not have write permissions
                        $msg = "Exception: " . $e->getMessage() . " writing merged changed from downloaded " . $cloudFile . " to file.";
                        logger($msg, LOGGER_DEBUG);
                        continue;
                    }

                    logger('merged box of contact ' . $c["xchan_name"] . ', title ' . $box["title"] . ' and wrote to file' . $box["boxID"] . '.json', LOGGER_DEBUG);
                } else {
                    logger('no changes after merging box of contact ' . $c["xchan_name"] . ', title ' . $box["title"] . '.', LOGGER_DEBUG);
                }
            }
        }

        if ($changed) {
            $box = $this->readBox($this->boxesDir, $boxID . '.json');
            json_return_and_die(array('status' => true, 'box' => $box));
        } else {
            json_return_and_die(array('status' => true));
        }
    }

    private function fetchBoxFromContact() {

        logger('+++ fetching box for contact ... +++', LOGGER_DEBUG);

        $url = $_POST['url'];
        if (!$url) {
            logger('no url in body of post given', LOGGER_DEBUG);
            return (array('status' => false, 'errormsg' => "no url given"));
        }

        $x = $this->fetchRessource($url);

        if (!$x['success']) {
            $msg = 'failed to fetch url ' . $url . " . Return code " . $x["return_code"];
            logger($msg, LOGGER_DEBUG);
            json_return_and_die(array('status' => false, 'errormsg' => $msg));
        }
    }

    private function fetchBoxesFromContact() {

        logger('+++ fetching boxes for contact ... +++', LOGGER_DEBUG);

        $url = $_POST['url'];
        if (!$url) {
            logger('no url in body of post given', LOGGER_DEBUG);
            return (array('status' => false, 'errormsg' => "no url given"));
        }

        $x = $this->fetchRessource($url);

        $ret = $this->readBoxFromResponse($x);

        if (!$ret['status']) {
            $msg = 'Failed to fetch url ' . $url . " . message " . $ret["errormsg"];
            logger($msg, LOGGER_DEBUG);
            json_return_and_die(array('status' => false, 'errormsg' => $msg));
        }

        $body["boxes"] = $ret["boxes"];
        $body["contact"] = $ret["contact"];

        logger('sending list of boxes from contact...', LOGGER_DEBUG);
        json_return_and_die(array('status' => true, 'body' => $body));
    }

    private function readBoxFromResponse($x) {

        $boxes = array();
        $contact = array();

        if (!$x['success']) {
            $msg = "Return code " . $x["return_code"];
            return(array('status' => false, 'errormsg' => $msg, 'boxes' => $boxes, 'contact' => $contact));
        }

        try {
            $bodyFetched = json_decode($x["body"], true);
        } catch (\Exception $e) {
            $msg = "Exception: " . $e->getMessage() . " when parsing body in response: " . $x["body"];
            return(array('status' => false, 'errormsg' => $msg, 'boxes' => $boxes, 'contact' => $contact));
        }

        if (!empty($bodyFetched['body'])) {
            $body["boxes"] = $bodyFetched["body"]["boxes"];
            $body["contact"] = $bodyFetched["body"]["contact"];
        } else {
            $msg = "no body in fetched url";
            return(array('status' => false, 'errormsg' => $msg, 'boxes' => $boxes, 'contact' => $contact));
        }

        if (is_null($body["boxes"])) {
            $msg = "no boxes found in body in fetched url";
            return(array('status' => false, 'errormsg' => $msg, 'boxes' => $boxes, 'contact' => $contact));
        }

        if (is_null($body["contact"])) {
            $msg = "no contact found in body in fetched url";
            return(array('status' => false, 'errormsg' => $msg, 'boxes' => $boxes, 'contact' => $contact));
        }

        $boxes = $body["boxes"];
        $contact = $body["contact"];

        return(array('status' => true, 'errormsg' => $msg, 'boxes' => $boxes, 'contact' => $contact));
    }

    private function readBoxes($is_sending) {

        $ret["status"] = true;
        $ret["errormsg"] = null;

        $baseURL = App::get_baseurl();

        $boxes = [];
        $nick = $this->owner['channel_address'];
        $dirFlashcards = new Directory($nick . '/flashcards/', [], $this->auth);
        $boxFiles = [];
        try {
            $boxFiles = $dirFlashcards->getChildren();
        } catch (\Exception $e) {
            logger('permission denied of empty directory for path ' . $nick . '/flashcards/. Exception: ' . $e->getMessage(), LOGGER_DEBUG);
            $ret["status"] = false;
            $ret["errormsg"] = "Exception. Causes a) no permission, or b) directory not found (addon 'flashcards' might be installed but never used?)";
        }
        foreach ($boxFiles as $child) {
            if ($child instanceof File) {
                if ($child->getContentType() === strtolower('application/json')) {
                    $fname = $child->getName();
                    logger('found json file = ' . $fname, LOGGER_DEBUG);
                    $box = $this->readBox($dirFlashcards, $fname);
                    if ($box) {
                        if ($this->hasCorrectedFileName($box, $fname)) {
                            $this->readBoxes($is_sending);
                        }
                        if ($box['cards']) {
                            $box['size'] = count($box['cards']);
                        } else {
                            $box['size'] = 0;
                        }
                        if ($is_sending) {
                            unset($box['cards']);
                            $box['owner'] = $this->owner['xchan_addr'];
                            $box['owner_xchan_hash'] = $this->owner['xchan_hash'];
                            $fn = substr($fname, 0, strpos($fname, '.'));
                            $box['url'] = $baseURL . '/flashcards/' . $nick . '/' . $fn;
                        }
                        array_push($boxes, $box);
                    }
                }
            }
        }
        if (empty($boxes)) {
            logger('no boxes found', LOGGER_DEBUG);
        }
        $ret["boxes"] = $boxes;

        return $ret;
    }

    private function hasCorrectedFileName($box, $filename) {
        $filenameExpected = $box["boxID"] . ".json";
        if ($filenameExpected !== $filename) {
            logger('File name of box ' . $filename . ' is not as expected ' . $filenameExpected);
            $this->boxesDir->createFile($filenameExpected, json_encode($box));
            logger('created box ' . $filename);

            if ($this->boxesDir->childExists($filename)) {
                // delete box itself
                $this->boxesDir->getChild($filename)->delete();
                logger('Deleted box ' . $filename);
            }
            return true;
        }
        return false;
    }

    private function sendContacts() {
        $contacts = $this->getContacts();

        logger("sending status=true, contacts", LOGGER_NORMAL);
        logger(print_r($contacts, true), LOGGER_DATA);
        json_return_and_die(array(
            'status' => true,
            'contacts' => $contacts));
    }

    private function getContacts() {
        $uid = $this->owner["channel_id"];
        load_contact_links($uid);
        $cs = App::$contacts;
        // xchan_url = "http://localhost/channel/a/list"
        // xchan_name = "a"
        // TODO:
        //    Explain in help text! The value can not be changed by the user.
        //    Objectiv: Share boxes only with closer contacts, not strangers.
        //    One could invent a settings page and store the settings in a
        //    json file, so it can easily synched between clones of channel. 
        $contacts = array();
        foreach ($cs as $c) {
            $affinity = $this->getCloseness($c['xchan_hash']);
            $url = $c['xchan_url'];
            $url_list_boxes = str_replace("/channel/", "/flashcards/", $url) . "/list";
            $url_cloud_box = str_replace("/channel/", "/cloud/", $url) . "/flashcards";

            // Mimic the xchan_addr "nick@domain" of Streams to keep
            // the JavaScript code consistent between Hubzilla and Streams
            $arr = explode("/", $c['xchan_url']);
            $c['xchan_addr'] = $c['xchan_name'] . "@" . $arr[2];
            $contacts[] = array('xchan_addr' => $c['xchan_addr'],
                'xchan_hash' => $c['xchan_hash'],
                'xchan_name' => $c['xchan_name'],
                'url_list_boxes' => $url_list_boxes,
                'url_cloud_box' => $url_cloud_box,
                'affinity' => $affinity,
                'is_owner' => ($c['xchan_hash'] === $this->owner['xchan_hash']));
        }
        return $contacts;
    }

    private function getCloseness($observer_xchan_hash) {
        $r = q("SELECT abook_closeness from abook WHERE abook_channel = %d AND abook_xchan = '%s'",
                intval($this->owner["channel_id"]),
                dbesc($observer_xchan_hash)
        );
        if ($r) {
            $closeness_found = $r[0]["abook_closeness"];
            return $closeness_found;
        }
        return 100;
    }

    private function readBoxidFromURL() {
        $box_id = "";
        if (argc() > 3) {
            $box_id = argv(3);
        } else {
            $errormsg = "No box id of found in url. Should be the 3rd part in path of url";
            logger('Stop: ' . $errormsg, LOGGER_DEBUG);
            json_return_and_die(array('status' => false, 'errormsg' => $errormsg), LOGGER_DEBUG);
        }

        if (strlen($box_id) != $this->lengthBoxId) {
            $errormsg = "No box id of lenght " + $this->lengthBoxId + " found in url. Found '" . $box_id . "'";
            logger('Stop: ' . $errormsg, LOGGER_DEBUG);
            json_return_and_die(array('status' => false, 'errormsg' => $errormsg), LOGGER_DEBUG);
        }
        return $box_id;
    }

    private function sendBox() {

        logger('+++ send box ... +++', LOGGER_DEBUG);

        if (!$this->observer) {
            json_return_and_die(array('status' => false, 'errormsg' => 'Unknown observer. Please login to view a box of flashcards '));
        }

        $box_id = $this->readBoxidFromURL();

        logger('user requested box id = ' . $box_id, LOGGER_DEBUG);

        $box = $this->readBox($this->boxesDir, $box_id . '.json');

        if (!$box) {

            logger('box not found or no permission, box id = ' . $box_id, LOGGER_DEBUG);

            json_return_and_die(array('status' => false, 'errormsg' => 'No box found or no permissions for ' . $box_id), LOGGER_DEBUG);
        }

        $creator_xchan_hash = $box["creator_xchan_hash"];
        if (!$creator_xchan_hash) {
            logger('No creator (key = creator_xchan_hash)  of box found in box with boxID =  ' . $box["boxID"], LOGGER_DEBUG);
        }

        if ($box['cards']) {
            $box['size'] = count($box['cards']);
        } else {
            $box['size'] = 0;
        }

        $link = "/cloud/" . $this->owner['channel_address'] . "/flashcards/" . $box_id . ".json";
        logger('Sending box ' . $link);
        logger(print_r($box, true), LOGGER_DATA);

        json_return_and_die(
                array(
                    'status' => true,
                    'is_owner' => $this->observer['xchan_hash'] === $creator_xchan_hash,
                    'link' => $link,
                    'box' => $box,
                    'boxPublicID' => $box["boxPublicID"],
                    'boxID' => $box["boxID"]));
    }

    /**
     * Use cases
     * 
     * 1. Create a new box.
     * --------------------
     * 
     * How to decide?
     * Box contains emtpy boxID 
     * 
     * The box was either
     * - created by the user, or
     * - was imported from a contact. In this case the browser is downloading
     *   the box.
     * 
     * What to do?
     * Create a random boxID for the box and use it for the filename. The json
     * file is written.
     * 
     * 2. Merge an existing box?
     * -------------------------
     * 
     * How to decide?
     * Box contains a boxID AND the json file does exist.
     * 
     * The box was either
     * - modified by the user, or
     * - was syncronized with a contact. In this case the browser is downloading
     *   the box, merging it and then uploads it to here.
     * 
     * What to do?
     * 
     *   a) If the box does exist in the file system (default case):
     *      Merge the box with the one found in the file system.
     *   b) If the box does exist in the file system (might be deleted):
     *      Write the box to file.
     */
    private function writeBox() {

        //----------------------------------------------------------------------
        // Only the logged in owner can create a box of cards or make changes.
        // 
        // Q: Why is it not allowed that another user with write access to the
        //    cloud file of the owner can create a box or change them?
        // A: The reason lies in the merge of boxes. There is the public part
        //    of a box: the content. And there is privat part: the learning
        //    progress. The merge process is able to take care of the public content but what to
        //    do with the learning progress of the user that is not the ower. Where
        //    to store the file? An older version of the addon copied the whole
        //    box and used it to store the learning progress in the copy. This
        //    way it was possible that every logged in user could learn despite
        //    of having installed the addon or not. But it
        //    comlicates things and blows up the code. The solution is:
        //    Every user has to install the addon and keep copies of a shared box as
        //    own cloud file. This corresponds
        //    better to the idea of decentralization that Hubzilla is built upon.
        //    If a box of flashcards is shared between users then copies do
        //    exist in different places, probably on different servers too. This
        //    makes the whole thing more resilient escpecially if a user has
        //    clones of his account. And, it follows the idea that knowledge is
        //    shared and kept public.
        //    
        if (!$this->is_owner) {
            $message = "Only the owner can write or update the box.";
            logger('Stop: ' . $message, LOGGER_DEBUG);
            json_return_and_die(array('status' => false, 'errormsg' => $message));
        }

        $boxRemote = $_POST['box'];
        if (!$boxRemote) {

            logger('no remote box given', LOGGER_DEBUG);

            json_return_and_die(array('status' => false, 'errormsg' => 'No box was sent'));
        }

        $box_id = $boxRemote["boxID"];

        $cards = $boxRemote['cards'];

        // The browser sends all cards or just a single card or some of them.
        $cardIDsReceived = array();
        if (isset($cards)) {
            foreach ($cards as &$card) {
                array_push($cardIDsReceived, $card['content'][0]);
            }
        }


        if (strlen($box_id) == $this->lengthBoxId) {

            $filename = $box_id . '.json';
            if (!$this->boxesDir->childExists($filename)) {

                //--------------------------------------------------------------
                // Use case:
                // 
                // The box is stored in the local storage of the webbrowser and
                // the clould file (JSON) was deleted meanwhile on the server.
                // The response action "restored" will trigger the browser to
                // store the whole box (all cards).
                //
                logger('Box was probably deleted. Writing/restoring box ' . $filename, LOGGER_DEBUG);

                $this->boxesDir->createFile($filename, json_encode($boxRemote));

                logger('Wrote/restored box ' . $filename);

                $boxRemote['lastShared'] = round(microtime(true) * 1000);
                unset($boxRemote['cards']);

                json_return_and_die(
                        array(
                            'status' => true,
                            'box' => $boxRemote,
                            'boxID' => $box_id,
                            'cardIDsReceived' => $cardIDsReceived,
                            'action' => "restored"));
            } else {

                //--------------------------------------------------------------
                // Use case:
                // 
                // The owner of the box changed the box: either by adding or
                // changing cards, or having a learnt cards.
                //
                logger('local box has to be merged with the box sent from web browser, box = ' . $filename, LOGGER_DEBUG);

                $this->mergeBox($box_id, $boxRemote, $cardIDsReceived);
            }
        } else {

            $action = "";

            $hash = random_string($this->lengthBoxId);
            $boxRemote["boxID"] = $hash;
            if (strlen($boxRemote["boxPublicID"]) != $this->lengthBoxId) {
                //--------------------------------------------------------------
                // Use case:
                // 
                // The user created the box.
                //
                logger('This box has just created (not imported from a contact) and will be written to file ' . $hash . '.json', LOGGER_DEBUG);
                $boxRemote["boxPublicID"] = $hash;
                $boxRemote["creator"] = $this->owner['xchan_addr'];
                $boxRemote["creator_xchan_hash"] = $this->owner['xchan_hash'];
                $action = "created";
            } else {
                //--------------------------------------------------------------
                // Use case:
                // 
                // The user imported the box from a contact.
                //
                logger('This box is imported from a  contact and will be written to file ' . $hash . '.json', LOGGER_DEBUG);
                $action = "imported";
            }
            $this->boxesDir->createFile($hash . '.json', json_encode($boxRemote));

            logger('The box was written to ' . $hash . '.json', LOGGER_DEBUG);

            $boxRemote['lastShared'] = round(microtime(true) * 1000);
            unset($boxRemote['cards']);

            json_return_and_die(
                    array(
                        'status' => true,
                        'box' => $boxRemote,
                        'boxID' => $hash,
                        'boxPublicID' => $boxRemote["boxPublicID"],
                        'cardIDsReceived' => $cardIDsReceived,
                        'action' => $action));
        }
    }

    private function readBox($dir, $filename) {
        $boxFileExists = $dir->childExists($filename);
        if (!$boxFileExists) {
            logger('file does not exist in boxes dir, file = ' . $filename, LOGGER_DEBUG);
            return false;
        }

        logger('read box and convert from file = ' . $filename, LOGGER_DEBUG);

        $JSONstream = $dir->getChild($filename)->get();
        $contents = stream_get_contents($JSONstream);
        $box = json_decode($contents, true);
        fclose($JSONstream);

        return $box;
    }

    private function mergeBox($box_id, $boxRemote, $cardIDsReceived) {

        $boxLocal = $this->readBox($this->boxesDir, $box_id . '.json');
        if (empty($boxLocal)) {
            json_return_and_die(array('status' => false, 'boxID' => $box_id, 'errormsg' => 'Box not found on server or failed to load'));
        }

        // Merge the changes from the client (browser)
        $boxes = $this->flashcards_merge($boxLocal, $boxRemote);
        $boxToWrite = $boxes['boxLocal'];
        $boxToSend = $boxes['boxRemote'];

        $boxToWrite['lastShared'] = $boxToSend['lastShared'] = round(microtime(true) * 1000);

        $fileName = $box_id . '.json';
        try {
            $this->boxesDir->getChild($fileName)->put(json_encode($boxToWrite));
        } catch (\Exception $e) {
            $msg = "Exception: " . $e->getMessage() . " writing merged box to file " . $fileName;
            logger($msg, LOGGER_DEBUG);
            json_return_and_die(
                    array(
                        'status' => false,
                        'errormsg' => $msg));
        }

        unset($boxToSend['cards']);
        logger('Merged and stored box...  Box id = ' . $box_id);
        json_return_and_die(
                array(
                    'status' => true,
                    'box' => $boxToSend,
                    'boxID' => $box_id,
                    'cardIDsReceived' => $cardIDsReceived,
                    'action' => "merged"));
    }

    private function deleteBox() {

        logger('+++ delete box ... +++', LOGGER_DEBUG);

        $boxID = $this->readBoxidFromURL();

        $filename = $boxID . '.json';

        if ($this->boxesDir->childExists($filename)) {

            // delete box itself
            $this->boxesDir->getChild($filename)->delete();
            // delete directory of box for the observers and their boxes too
            if ($this->boxesDir->childExists($boxID)) {
                $this->boxesDir->getChild($boxID)->delete();
            }
            logger('Deleted box ' . $filename);
            json_return_and_die(array('status' => true));
        } else {
            logger('Failed to delete box. No file ' . $filename);
            json_return_and_die(array('status' => false, 'errormsg' => 'Box not found on server'));
        }
    }

    private function initAuth() {

        // Thoughts...
        //
        // Keep more tricky constallations in mind like:
        // 
        // Provided...
        // 
        // - A is the owner of https//my-hub.org/flashcards/a)
        // - B is the observer  visiting https//my-hub.org/flashcards/a
        // - C is a contact of A
        // 
        // B ist the observer at this point in the code here.
        // The server of A and C will apply the permissions
        // of B on their servers for the resources (boxes = cloud files for example).
        // To illustrate it a bit: B is asking "in the name of" A to
        // have a list of boxes from C server to server to pull changes.
        // The fetch would be successfull but to write the merges into the JSON
        // file representing the box of A, user B must also have write permissions
        // to the cloud file of A. In praxis this might be indended (?).
        // 
        // There is also the case that user is not logged in at all. No observer
        // is know in this case.
        //
        // copied/adapted from Cloud.php
        $this->auth = new BasicAuth();

        $ob_hash = get_observer_hash();

        if ($ob_hash) {
            if (local_channel()) {
                $channel = \App::get_channel();
                $this->auth->setCurrentUser($channel['channel_address']);
                $this->auth->channel_account_id = $channel['channel_account_id'];
                $this->auth->channel_id = $channel['channel_id'];
                $this->auth->channel_hash = $channel['channel_hash'];
                if ($channel['channel_timezone'])
                    $this->auth->setTimezone($channel['channel_timezone']);
            }
            $this->auth->observer = $ob_hash;
        }
    }

    private function getAddonDir() {

        $this->getRootDir(); // just test this time

        $channelAddress = $this->owner['channel_address'];

        $channelDir = new Directory('/' . $channelAddress, [], $this->auth);

        if (!$channelDir->childExists('flashcards') && $this->is_owner) {
            $channelDir->createDirectory('flashcards');
        }

        $this->boxesDir = new Directory('/' . $channelAddress . '/flashcards', [], $this->auth);
        if (!$this->boxesDir) {
            json_return_and_die(array('message' => 'no directory flashcards or no permission', 'success' => false));
        }
    }

    private function getRootDir() {

        $rootDirectory = new Directory('/', [], $this->auth);

        $channelAddress = $this->owner['channel_address'];

        if (!$rootDirectory->childExists($channelAddress)) {
            json_return_and_die(array('message' => 'No cloud directory.', 'success' => false));
        }

        return $rootDirectory;
    }

    private function fetchRessource($url) {
        logger('fetching ressource ' . $url, LOGGER_DEBUG);
        $recurse = 0;
        $x = z_fetch_url(zid($url), false, $recurse, ['session' => true]);
        return $x;
    }

    private function clearBox($box) {
        $box["private_autosave"] = true;
        $box["private_show_card_sort"] = false;
        $box["private_sort_default"] = true;
        $box["private_search_convenient"] = true;
        $box["private_switch_learn_all"] = false;
        $box["private_switch_learn_direction"] = false;
        $box["private_hasChanged"] = false;
        $box["private_sortColumn"] = 0;
        $box["private_sortReverse"] = false;
        $box["lastChangedPrivateMetaData"] = round(microtime(true) * 1000);
        $box["cardsDecks"] = 7;
        $box["cardsDeckWaitExponent"] = 3;
        $box["cardsRepetitionsPerDeck"] = 3;
        $box["private_block"] = false;

        $box["private_visibleColumns"][0] = false;
        for ($i = 1; $i < 3; $i++) {
            $box["private_visibleColumns"][$i] = true;
        }
        for ($i = 3; $i < 11; $i++) {
            $box["private_visibleColumns"][$i] = false;
        }

        for ($i = 0; $i < sizeof($box['cards']); $i++) {
            for ($j = 6; $j < 10; $j++) {
                $box['cards'][$i]['content'][$j] = 0;
            }
            $box['cards'][$i]['content'][10] = false;
        }

        return $box;
    }

    /*
     * Merge to boxes of flashcards
     *
     *  compare
     *  - boxPublicID > if not equal then do nothing
     *  do not touch
     *  - boxPublicID
     *  - boxID
     *  - creator
     *  - creator_xchan_hash
     *  - lastShared
     *  - maxLengthCardField
     *  - cardsColumnName
     *  - hasChanged
     *  - block_changes
     *  - license_public_domain
     *  lastChangedPublicMetaData
     *  - title
     *  - description
     *  - lastEditor
     *  lastChangedPrivateMetaData
     *  - cardsDecks
     *  - cardsDeckWaitExponent
     *  - cardsRepetitionsPerDeck
     *  - private_sortColumn
     *  - private_sortReverse
     *  - private_filter
     *  - private_visibleColumns
     *  - private_switch_learn_direction
     *  - private_switch_learn_all
     *  - private_autosave
     *  - private_show_card_sort
     *  - private_sort_default
     *  - private_search_convenient
     *  - private_block
     *  calculate
     *  - size
     *  cards
     *  0 - id = creation timestamp, milliseconds, Integer > do not touch
     *  1 - Language A, String > last modified content
     *  2 - Language B, String > last modified content
     *  3 - Description, String > last modified content
     *  4 - Tags, "Lesson 010.03" or anything else, String > last modified content
     *  5 - last modified content, milliseconds, Integer
     *  6 - Deck, Integer from 0 to 6 but configurable > last modified progress
     *  7 - progress in deck default 0, Integer > last modified progress
     *  8 - How often learned (information for the user only), Integer > last modified progress
     *  9 - last modified progress, milliseconds, Integer
     *  10 - has local changes, Boolean > use to create new box to send
     *
     * @param $boxLocal array from local DB
     * @param $boxRemote array received to merge with box in DB
     */

    function flashcards_merge($boxLocal, $boxRemote, $is_private = true) {

        logger('merge boxes local id = ' . $boxLocal['boxID'] . ', remote id = ' . $boxRemote['boxID'], LOGGER_DEBUG);

        $has_boxLocal_changes = false;
        if ($boxLocal['lastChangedPublicMetaData'] < $boxRemote['lastChangedPublicMetaData']) {
            $has_boxLocal_changes = true;
        }

        if ($is_private) {
            if ($boxLocal['boxID'] != $boxRemote['boxID']) {
                unset($boxRemote['cards']);
                return array('boxLocal' => $boxLocal, 'boxRemote' => $boxRemote);
            }
        } else {
            if ($boxLocal['boxPublicID'] != $boxRemote['boxPublicID']) {
                unset($boxRemote['cards']);
                return array('boxLocal' => $boxLocal, 'boxRemote' => $boxRemote);
            }
        }
        $keysPublic = array('title', 'description', 'lastEditor', 'lastChangedPublicMetaData', 'lastShared', 'license_public_domain');
        $keysPrivate = array('cardsDecks', 'cardsDeckWaitExponent', 'cardsRepetitionsPerDeck', 'private_block', 'private_sortColumn', 'private_sortReverse', 'private_filter', 'private_visibleColumns', 'private_switch_learn_direction', 'private_switch_learn_all', 'private_autosave', 'private_show_card_sort', 'private_sort_default', 'private_search_convenient', 'lastChangedPrivateMetaData');
        if ($boxLocal['lastChangedPublicMetaData'] != $boxRemote['lastChangedPublicMetaData']) {
            if ($boxLocal['lastChangedPublicMetaData'] > $boxRemote['lastChangedPublicMetaData']) {
                foreach ($keysPublic as &$key) {
                    $boxRemote[$key] = $boxLocal[$key];
                }
            } else {
                foreach ($keysPublic as &$key) {
                    $boxLocal[$key] = $boxRemote[$key];
                }
            }
        }
        if ($is_private) {
            if ($boxLocal['lastChangedPrivateMetaData'] != $boxRemote['lastChangedPrivateMetaData']) {
                if ($boxLocal['lastChangedPrivateMetaData'] > $boxRemote['lastChangedPrivateMetaData']) {
                    foreach ($keysPrivate as &$key) {
                        $boxRemote[$key] = $boxLocal[$key];
                    }
                } else {
                    foreach ($keysPrivate as &$key) {
                        $boxLocal[$key] = $boxRemote[$key];
                    }
                }
            }
        }
        $cardsDB = $boxLocal['cards'];
        if (!$cardsDB) {
            $cardsDB = [];
        }
        $cardsRemote = $boxRemote['cards'];
        if (!$cardsRemote) {
            $cardsRemote = [];
        }
        $cardsDBadded = array();
        $cardsRemoteToUpload = array();
        foreach ($cardsRemote as &$cardRemote) {
            $isInDB = false;
            foreach ($cardsDB as &$cardDB) {
                if ($cardRemote['content'][0] == $cardDB['content'][0]) {
                    $isInDB = true;
                    $isRemoteChanged = false;
                    if ($cardDB['content'][5] != $cardRemote['content'][5]) {
                        if ($cardDB['content'][5] > $cardRemote['content'][5]) {
                            for ($i = 1; $i < 6; $i++) {
                                $cardRemote['content'][$i] = $cardDB['content'][$i];
                                $isRemoteChanged = true;
                            }
                        } else {
                            for ($i = 1; $i < 6; $i++) {
                                $cardDB['content'][$i] = $cardRemote['content'][$i];
                            }
                            $has_boxLocal_changes = true;
                        }
                    }
                    if ($is_private) {
                        if ($cardDB['content'][9] != $cardRemote['content'][9]) {
                            if ($cardDB['content'][9] > $cardRemote['content'][9]) {
                                for ($i = 6; $i < 10; $i++) {
                                    $cardRemote['content'][$i] = $cardDB['content'][$i];
                                    $isRemoteChanged = true;
                                }
                            } else {
                                for ($i = 6; $i < 10; $i++) {
                                    $cardDB['content'][$i] = $cardRemote['content'][$i];
                                }
                            }
                        }
                    }
                    if ($isRemoteChanged === true) {
                        array_push($cardsRemoteToUpload, $cardDB);
                    }
                    break;
                }
            }
            if (!$isInDB) {
                if (!$is_private) {
                    for ($i = 6; $i < 10; $i++) {
                        $cardRemote['content'][$i] = 0;
                    }
                    $cardRemote['content'][10] = false;
                }
                $has_boxLocal_changes = true;
                array_push($cardsDBadded, $cardRemote);
            }
        }
        // Add cards from local DB that are not in the remote cards
        $lastShared = $boxRemote['lastShared'];
        foreach ($cardsDB as &$cardDB) {
            $isInRemote = false;
            foreach ($cardsRemote as &$cardRemote) {
                if ($cardRemote[0] == $cardDB[0]) {
                    $isInRemote = true;
                    break;
                }
            }
            if (!$isInRemote) {
                if ($lastShared < $cardDB['content'][5]) {
                    array_push($cardsRemoteToUpload, $cardDB);
                } else if ($lastShared < $cardDB['content'][9]) {
                    array_push($cardsRemoteToUpload, $cardDB);
                }
            }
        }
        // Check if the same user change a card on a different client (browser)
        $cardsDB = array_merge($cardsDB, $cardsDBadded);
        $boxLocal['size'] = count($cardsDB);
        $boxRemote['size'] = count($cardsDB);
        $boxLocal['cards'] = $cardsDB;
        $boxRemote['cards'] = $cardsRemoteToUpload; // send changed or new cards only

        logger('merge boxes finished', LOGGER_DEBUG);

        return array('boxLocal' => $boxLocal, 'boxRemote' => $boxRemote, 'changed' => $has_boxLocal_changes);
    }

    function flashcards_merge_test() {
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[]}';
        $boxIn2 = '{"boxID":"b2b2b2","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_autosave":true,"private_show_card_sort":true,"private_sort_default":true,"private_search_convenient":true,"hasChanged":false,"private_block":true,"license_public_domain":true,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare2 = '{"boxID":"b2b2b2","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":"b","size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_autosave":true,"private_show_card_sort":true,"private_sort_default":true,"private_search_convenient":true,"hasChanged":false,"private_block":true,"license_public_domain":true}';
        $box2['boxPublicID'] = 'b';
        $boxes = $this->flashcards_merge($box1, $box2, false);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxIn1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Nothing changed
        $box2['boxPublicID'] = $box1['boxPublicID'];
        $boxCompare2 = '{"boxID":"b2b2b2","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_autosave":true,"private_show_card_sort":true,"private_sort_default":true,"private_search_convenient":true,"hasChanged":false,"private_block":true,"license_public_domain":true}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxIn1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Public and private meta data
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":true,"license_public_domain":true,"cards":[]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_autosave":true,"private_show_card_sort":true,"private_sort_default":true,"private_search_convenient":true,"hasChanged":false,"private_block":false,"license_public_domain":false,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":true,"license_public_domain":false,"cards":[]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":false,"private_block":true,"license_public_domain":false,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Public and private meta data the other way around
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":true,"license_public_domain":true,"cards":[]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":true,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_autosave":true,"private_show_card_sort":true,"private_sort_default":true,"private_search_convenient":true,"hasChanged":false,"private_block":false,"license_public_domain":false,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":true,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_autosave":true,"private_show_card_sort":true,"private_sort_default":true,"private_search_convenient":true,"hasChanged":true,"private_block":false,"license_public_domain":true,"cards":[]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":true,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_autosave":true,"private_show_card_sort":true,"private_sort_default":true,"private_search_convenient":true,"hasChanged":false,"private_block":false,"license_public_domain":true,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Add remote card to empty local cards
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Add local cards to empty remote cards
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":999,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":999,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // change card values
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230161,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // change card values the other way around
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230161,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }
        // add public card to box 1 (local)
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,0,false]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,0,0,0,0,false]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2, false);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }

        // last shared younger than last learnt
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230162,0,0,0,1531058230160,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230162,1,2,3,1531058230162,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230162,1,2,3,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }

        // last shared younger than last edit
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230160,0,0,0,1531058230162,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }

        // last shared older than last edit and last learnt
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230160,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230160,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }

        // last shared older than last edit
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }

        // last shared older than last learnt
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_autosave":false,"private_show_card_sort":false,"private_sort_default":false,"private_search_convenient":false,"hasChanged":true,"private_block":false,"license_public_domain":false,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if ($boxOut1 !== $boxCompare1) {
            return false;
        }
        if ($boxOut2 !== $boxCompare2) {
            return false;
        }

        logger('tests all passed');
        return true;
    }
}

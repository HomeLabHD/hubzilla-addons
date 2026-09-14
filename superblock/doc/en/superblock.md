## Superblock

The Superblock app enables you to block other channels from your network
stream, notifications, private messages and mentions. Once a channel is
blocked, it should be virtually invisible to you.

You can optionally set a duration for the block to make it temporary. After the
given duration the block will expire, and posts from the channel will be
visible again.

### Blocking channels from your network stream

If you see a post or comment from someone you don't want to see anything from
again in your streams, you can block them by clicking their avatar picture
and select "Block channel…".

A dialog will pop up giving you the option to set a duration for the block. To
make the block permanent, just leave the duration at 0.

### Manually adding channels to the block list

You can manually add channels to the block list by visiting the [baseurl]/superblock
page, click on "Add new entry", and then type the channel address into the text field
that appears.

This allows you to proactively block channels even before you see any content from
them in your streams.

Note that for now, only channels known to your hub can be blocked this way.

### Editing a channel block

If you wish to change the expiry time for a channel block, you can click the
pencil icon next to the entry in the channel block list at [baseurl]/superblock.

### Unblocking a channel

To remove a channel from the block list, click the trashcan icon to the right of
the entry when visiting the [baseurl]/superblock page.

### Blocking a channel from the site (admins only)

A site admin can add a channel to the site wide block list by clicking on the avatar
picture in a post or comment by the channel they want to block, and select "Block
from site".

This feature is only available to site admins.

### Settings

You can access the Superblock app settings by clicking on the cogwheel next to
the app title (upper left corner) when visiting the main Superblock app page.

#### Block reshares from blocked channels

This setting determines if Superblock will block posts containing a reshare from a channel that is on the block list. When enabled, this setting will ensure that such reshares are blocked, even when reshared by a channel that is not itself blocked. By disabling this setting, such reshares will be visible in the timeline.

This setting is **enabled** by default.

#### BLock incoming posts and activities

This setting determines if Superblock will discard incoming activities from blocked channels. By disabling this setting, Superblock will only prevent the posts and activities from being displayed to you, but they will still be stored and kept in the channel, and may reappear if the block expires or the channel unblocked.

This setting is **enabled** by default.

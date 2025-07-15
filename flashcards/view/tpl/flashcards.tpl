<div class="generic-content-wrapper" id="panel_box_navigation" style="display: none;">
    <div class="section-title-wrapper clearfix">
        <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle flashcards_nav" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Menu"  id="button_flashcards_menu">
                <i class="bi bi-mortarboard"></i>
            </button>
            <div class="dropdown-menu" style="position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate(0px, 30px);" data-popper-placement="bottom-end">
                <a class="dropdown-item" id="flashcards_new_box"><i class="bi bi-file-earmark-plus h2"></i> New Box</a>
                <a class="dropdown-item" id="flashcards_edit_box"><i class="bi bi-pencil h2"></i> Edit Box</a>
                <a class="dropdown-item" id="flashcards_show_boxes"><i class="bi bi-search h2"></i> List Boxes</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" id="flashcards_show_help"><i class="bi bi-question h2"></i> Help</a>
            </div>

            <span id="flashcards_navbar_brand" class="flashcards_nav">Flashcards</span>
            <button class="btn  btn-default  btn-sm flashcards_nav" id="button_flashcards_learn_play"><i class="bi bi-play"></i> <sup><span id="span_flashcards_cards_due"></span></sup></button>

            <button class="btn btn-default btn-sm" id="button_share_box">
                <i class="bi bi-arrow-repeat"></i>
                <span id="button_share_box_counter"></span>
            </button>
            <button class="btn btn-default btn-sm" id="button_flashcards_save_box" style="display: none;" {{if !$has_write_permission}}disabled{{/if}}>
                <i class="bi bi-save"></i> Save
            </button>
            <button class="btn btn-default btn-sm" id="button_flashcards_close" style="white-space: nowrap;">
                <i class="bi bi-x-lg"></i> Close
            </button>
        </div>
    </div>
    <div class="connections-wrapper clearfix">
        <div id="page-end"></div>
    </div>
    <button class="btn btn-default btn-sm" id="button_sync_box" style="display:none;color:green;">
        <i class="bi bi-arrow-repeat"></i>
        <span id="button_sync_box_contact"></span>
    </button>
</div>

<div class="d-flex justify-content-center" id="flashcards_panel_learn_buttons">
    <div class="p-2">
        <button class="btn flashcards_learn" id="button_flashcards_learn_stopp"><i class="bi bi-stop"></i></button>
    </div>
    <div class="p-2">
        <button class="btn flashcards_learn" id="button_flashcards_learn_next"><i class="bi bi-step-forward"></i></button>
    </div>
    <div class="p-2">
        <button class="btn flashcards_learn" id="button_flashcards_learn_passed"><i class="bi bi-hand-thumbs-up"></i></button>
    </div>
    <div class="p-2">
        <button class="btn flashcards_learn" id="button_flashcards_learn_failed"><i class="bi bi-hand-thumbs-down"></i></button>
    </div>
</div>

<div id="panel_box_attributes" class="panel-collapse collapse">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="form-group">
                    <label for="flashcards_box_title">Title:</label>
                    <input class="form-control" id="flashcards_box_title" name="title" required minlength="10" maxlength="60">
                    <small class="form-text text-muted">Short descriptive title (between 10 to 60 characters)</small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="form-group">
                    <label for="flashcards_box_description">Description:</label>
                    <textarea class="form-control" rows="5" id="flashcards_box_description" name="description" required minlength="10" maxlength="800"></textarea>
                    <small class="form-text text-muted">Description of box (between 10 to 800 characters)</small>
                </div>
            </div>
        </div>
        <div class="row" id="flashcards-is-public-domain-row">
            <div class="col-sm-12">
                <label><input type="checkbox" id="flashcards-is-public-domain"> Public domain license</label>
            </div>
        </div>
        <div class="row" id="flashcards-block-changes-row">
            <div class="col-sm-12">
                <label><input type="checkbox" id="flashcards-block-changes"> Only I am allowed to make changes to this box</label><small> (Do not pull updates from contacts.)</small>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="form-group">
                    <small class="form-text text-muted">Creator is </small>
                    <small id="flashcards_creator" class="form-text text-muted"></small>
                    <small id="flashcards_owner" class="form-text text-muted">, owner is {{$flashcards_owner}}. </small>
                    <small id="flashcards_editor" class="form-text text-muted">{{$flashcards_editor}}</small>
                    <small id="flashcards_link" class="form-text text-muted"></small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-2">
                <button class="btn" data-bs-toggle="collapse" href="#" role="button" aria-expanded="false" data-bs-target="#panel_flashbox_settings"><i class="bi bi-gear"></i> Settings</button>
            </div>
        </div>
        <div id="panel_flashbox_settings" class="panel-collapse collapse">
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <h2>Settings</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-10">
                    Restore all settings below to default values
                </div>
                <div class="col-sm-2">
                    <button class="btn" id="button_flashcards_settings_default"><i class="bi bi-x-lg"></i> Restore</button>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <label><input type="checkbox" id="flashcards-autosave"> Automatically upload changes (sync) </label>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <label><input type="checkbox" id="flashcards-convenient-search"> Convenient search </label>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <label><input type="checkbox" id="flashcards-card-sort"> Show sortation </label>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <h2>How to learn</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <label><input type="checkbox" id="flashcards-switch-learn-directions"> Switch learn direction (sides of cards) </label>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <label><input type="checkbox" id="flashcards-default-sort"> Always start to learn with first box (Leitner default)</label>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <label><input type="checkbox" id="flashcards-switch-learn-all"> Learn all displayed cards no matter wether due to learn or not (switch off Leitner / spaced repetition) </label>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <h2>Adapt the Learn System</h2>
                    <br>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12" id="flashcards-learn-system-visualisation"></div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                    You can refine the <a href="https://en.wikipedia.org/wiki/Leitner_system" target="_blank">Leitner System</a> by setting the variables below.
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <label for="flashcards-learn-system-decks"><br>Number of Decks</label>
                        <input type="number" class="form-control flashcards-learn-params" id="flashcards-learn-system-decks" placeholder="7" min="4" max="10">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <label for="flashcards-learn-system-deck-repetitions">Repetitions per deck (classic Leitner is "1")</label>
                        <input type="number" class="form-control flashcards-learn-params" id="flashcards-learn-system-deck-repetitions" placeholder="3" min="1" max="10">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <label for="flashcards-learn-system-exponent">Exponent to calculate the wait time inside a deck... <span id="fc_leitner_calculation"></span></label>
                        <input type="number" class="form-control flashcards-learn-params" id="flashcards-learn-system-exponent" placeholder="3" min="1" max="5">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <hr/>
                    <h2>Visibility of Card Details</h2>
                    ...in the 'big' table<br><br>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="0"> card - id = creation time</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="1"> card - side 1</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="2"> card - side 2</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="3"> card - description</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="4"> card - tags</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="5"> card - last modified</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="6"> learn progress - deck</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="7"> learn progress - status inside deck</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="8"> learn progress - how often learned</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="9"> learn progress - time last learnt</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" class="flashcards-column-visibility" col="10"> has local changes for upload</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="panel_flashcards_card" class="panel-collapse collapse">
	<div class="panel panel-default">
		<div class="panel-heading flashcards_nav" id="flashcards_panel_card_header">
		    <h2 class="panel-title">
				Card
				<button class="btn" id="flashcards_cardedit_save"><i class="bi bi-save"></i></button>
				<button class="btn" id="flashcards_cardedit_cancel"><i class="bi bi-x-lg"></i></button>
		    </h2>
		</div>
		<div id="flashcards_main_card">
            <div class="container-fluid">
              <div class="row">
                <div class="col-sm-6">
                     <div class="form-group">
                      <label for="flashcards_language1">Side 1:</label>
                      <textarea class="form-control card-content" rows="5" id="flashcards_language1"></textarea>
                    </div>
                </div>
                <div class="col-sm-6">
                     <div class="form-group">
                      <label for="flashcards_language2">Side 2:</label>
                      <textarea class="form-control card-content" rows="5" id="flashcards_language2"></textarea>
                    </div>
                </div>
              </div>
              <div class="row">
                <div class="col-sm-12">
                     <div class="form-group">
                      <label for="flashcards_description">Description:</label>
                      <textarea class="form-control card-content" rows="5" id="flashcards_description"></textarea>
                    </div>
                </div>
              </div>
              <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                      <label for="flashcards_tags">Tags:</label>
                      <input class="form-control card-content" id="flashcards_tags">
                    </div>
                </div>
              </div>
              <div class="row">
                <div class="col-sm-12">
                     <small class="form-text text-muted" id="flashcard_learn_card_details"></small>
                </div>
              </div>
            </div>
		</div>
	</div>
</div>

<div id="panel_flashcards_permissions" class="panel-collapse collapse">
</div>

<div id="panel_flashcards_cards_actions" style="display: none;">
	<span class="navbar-brand">
            <div class="container-fluid">
              <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <button class="nav-item btn btn-default" id="button_flashcards_search_cards" style="display: none;">
                            <i class="bi bi-search"></i>
                        </button>
                        <button class="nav-item btn btn-default" id="button_flashcards_new_card">
                            <i class="bi bi-calendar-date"></i>
                        </button>
                        <span id="span_flashcards_cards_actions_status"></span>
                        <span>Cards</span>
                    </div>
                </div>
              </div>
              <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <input id="input_flashcards_search_cards" style="display: none;">
                    </div>
                </div>
              </div>
            </div>
	</span>
</div>

<div id="panel_flashcards_cards" style="display: none;"></div>

<div id="panel_cloud_boxes_1" class="cloud-tool" style="display: block;display: none;">

    <div class="panel" style="display: {{$slide}};">

        <div class="section-content-tools-wrapper" >
            <span class="bi bi-person-fill h2"></span>
            <input id="slider_flashcards_affinity" type="range" min="0" max="100" value="{{$val}}" list="steplist" oninput="sliderChanged(this.value)" style='width:90%'>
            <span class="bi bi-globe-asia-australia h2"></span>
            <datalist id="steplist">
                <option>0</option>
                <option>20</option>
                <option>40</option>
                <option>60</option>
                <option>80</option>
                <option>100</option>
            </datalist>
        </div>
    </div>

    <div id="panel_list_boxes_header"></div>
    <div id="panel_list_boxes"></div>
</div>

<div id="panel_flashcards_help" style="display: none;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <p>Flashcards version <span id="flashcards_version">{{$flashcards_version}}</span></p>
                <p>This addon is a  <a href="https://en.wikipedia.org/wiki/List_of_flashcard_software" target="_blank">flashcard software</a> that uses <a href="https://en.wikipedia.org/wiki/Spaced_repetition" target="_blank">spaced repetition</a> as a learning technique.</p>
                <p><img src="/addon/flashcards/view/img/leitner-system.png" align="center" width="70%"></p>
                <p>Share your boxes of flash cards with other users.</p>
                <p>Your learning progress is kept private.</p>
                <p>Use as dictionary.</p>
                <p>Change the learning algorithm.</p>
                <hr/>
                <h2>The School Example</h2>
                <hr/>
                <p>To illustrate things a bit, imagine the three actors:</p>
                <ul>
                    <li>A school</li>
                    <li>Anna, a student of the school. Anna is a contact of the school. (Both are "connected" to each other.)</li>
                    <li>Bert, a friend of Anna. Bert is a contact of Anna (but not a contact of the school). </li>
                </ul>
                <p></p>
                <h3>The School...</h3>
                <p>...has created a box of flashcards, let's say "English-Italian".</p>
                <p>The school has full control over who is allowed:</p>
                <ul>
                    <li>To use "English-Italian"</li>
                    <li>To make changes to "English-Italian"</li>
                </ul>
                <p></p>
                <h3>Anna, The Student...</h3>
                <p>...is able to:</p>
                <ul>
                    <li>View "English-Italian"</li>
                    <li>Use "English-Italian" as dictionary</li>
                    <li>Learn "English-Italian"</li>
                    <li>Change the learning algorithm, e.g. how often to repeat cards,...</li>
                    <li>Make changes to "English-Italian".
                        The changes will by written to the original cards of the school if the school
                        has allowed this, see checkbox "Only I am allowed to make changes to this box".</li>
                </ul>
                <p></p>
                <h3>Bert, The Friend Of Anna...</h3>
                <p>...has no access to the box "English-Italian"</p>
                <p>How to give access to Bert?</p>
                <ul>
                    <li>Either Bert must become a contact of the school, </li>
                    <li>Or The school marks the box "English-Italian" as "public domain"</li>
                </ul>
                <hr/>
                <h2>How Boxes Are Shared</h2>
                <hr/>
                <h3>How To Find Boxes Of Other Users?</h3>
                <p>When you <b>list boxes</b> by:</p>
                <ul>
                    <li>Starting the app Flashcards, or</li>
                    <li>Via menu "List Boxes"</li>
                </ul>
                <p></p>
                <p>...the app will:</p>
                <ol>
                    <li>
                        Collect a list of your contacts that:
                        <ul>
                            <li>Have the app (addon Flashcards) installed AND</li>
                            <li>Have read permission to your boxes (cloud files) AND</li>
                            <li>Are close enough to you (Install the addon "Affinity" to use this feature.)</li>
                        </ul>
                    </li>
                    <li>Collect the boxes of those contacts.</li>
                </ol>
                <p></p>
                <p>Troubleshooting if boxes of your contact(s) are not listed...</p>
                <ul>
                    <li>Draw the affinity slider (to the right) if you installed the addon "Affinity".</li>
                    <li>Check if you deleted the contact.</li>
                    <li>Check if you contact did un-friend (deleted) you.</li>
                    <li>Ask our contact to check if you have read permission to the clould file representing a box (JSON).</li>
                    <li>In case the creator of the box is not a direct contact of you:
                        Check if the creator has removed the license public domain.</li>
                </ul>
                <p></p>
                <h3>How Do You Receive Updates From Other Users?</h3>
                <p>When you <b>open a box</b> of flashcards...</p>
                <ol>
                    <li>The app will download this box from your contacts.</li>
                    <li>
                        The app will merge changes using last modified timestamps for:
                        <ul>
                            <li>the meta data (title, description,...)</li>
                            <li>every single card</li>
                        </ul>
                    </li>
                </ol>
                <p></p>
                <p>You can block receiving changes from other users. How?</p>
                <p>Use the checkbox "Only I am allowed to make changes to this box".</p>
                <p>Troubleshooting if you do not receive updates...</p>
                <ul>
                    <li>See troubleshooting above ("list boxes").</li>
                    <li>Keep in mind: The update of a box is always a pull
                        (download) of a box from your <b>direct</b> contacts.
                        You will not receive changes of a contact of a contact if
                        your direct contact does never open a box.
                        How do deal with it? Connect yourself with the user you want to receive changes from.
                    </li>
                    <li>
                        Do this check: List all boxes. Every box shows two sublists                        
                        <ul>
                            <li>You are pulling changes from,</li>
                            <li>Contacts pulling changes from you</li>
                        </ul>
                    </li>
                </ul>
                <p></p>
                <h3>How To Give (Read) Access To Your Boxes?</h3>
                <ul>
                    <li>Connect to somebody to add him/her to your contact list.</li>
                    <li>Make sure your contact has read permission to your cloud files, more precisely to the folder /cloud/nickname/flashcards.
                        (This is a built-in feature of Hubzilla. Use the app "Files".)</li>
                    <li>Make sure your contact has read permission to a box (JSON file) stored under  /cloud/nickname/flashcards.
                        This way you can fine tune who can access your boxes. (Cloud files and the permission to read/write them
                        is a built-in feature of Hubzilla. Use the app "Files".)</li>
                </ul>
                <p></p>
                <p>If a user is not connected directly to you but is a contact of a contact (...of a contact, of a contact,...)</p>
                <ul>
                    <li>Mark the box as public domain.</li>
                </ul>
                <hr/>
                <h2>Features</h2>
                <hr/>                
                <p>General information of a box:</p>                       
                <ul>
                    <li>Title... at least 3 characters,</li>
                    <li>Description</li>
                </ul>            
                <p></p>             
                <p>Use as dictionary</p>                       
                <ul>
                    <li>Convenient search (default): Seach your cards in side A, side B, description, tags.</li>
                    <li>Full search (optional): Search in single fields of the cards, e.g. in title or tags only.</li>
                    <li>Sort the cards by every field of the cards, e.g. creation timestamp, last learnt timestamp, tags,...</li>
                    <li>Show/hide (big table) the cards of box. </li>
                    <li>Choose the columns to display (big table).</li>
                </ul>         
                <p></p>    
                <p>Learning method (Leitner):</p>                       
                <ul>
                    <li>Switch the learn direction A->B or B->A.</li>
                    <li>Choose the wait time inside a deck.</li>
                    <li>Choose the number of repititions per deck.</li>
                    <li>Choose the number of decks.</li>
                    <li>Filter the cards to learn. Use the search for this.</li>
                    <li>Optionally learn cards despite they are due to learn or not. (Ingnore the Leitner Method = spaced repitition.)</li>
                    <li>Sort the cards by every field of the cards, e.g. creation timestamp, last learnt timestamp, tags,...</li>
                </ul>  
                <p></p>
                <p></p>             
                <p>Sharing:</p>                       
                <ul>
                    <li>Share boxes with your contacts (friends).</li>
                    <li>Optionally: Limit the number of contacts with their boxes you want to see
                        by using the affinity slider. Default affinity is 80 (0 - 100).</li>
                    <li>Optionally: Block changes made by your contacts. Default is "do not block".</li>
                    <li>
                        Optionally: License "public domain". Default is "public domain".
                        Only the creator of the box can change the license.
                        If a box is published unter "public domain" also a friend of a friend of a friend... will
                        see the box.
                    </li>
                    <li>
                        Optionally: Set different permission for single boxes for:                  
                        <ul>
                            <li>groups</li>
                            <li>single users</li>
                        </ul>
                        Use the app "Files" that is default app Hubzilla.
                    </li>  
                    <li>Your friends can change title and description of your box.</li>  
                    <li>Your friends can add cards to your box.</li>   
                    <li>Your friends can correct (change) cards of your box.</li>  
                </ul>     
                <p></p>                  
                <p>Backup and Restore:</p>                       
                <ul>
                    <li>Backup: Download a box, the JSON file representing a box.</li>
                    <li>Restore: Upload the JSON file representing the box to <code>/cloud/nickname/flashcards/</code></li>
                    <li>Restore: Import the box again if a contact uses the same box. Caveat: Your learning progress
                        will be lost.</li>
                    <li>Restore: A box is restored from the "local storage" of the web browser automatically in
                        case you delete the JSON file representing the box on the server by accident.</li>
                </ul>          
                <p></p>     
                <p>Specal Features:</p>                       
                <ul>
                    <li>A box is automatically synchronized to your clones if you have cloned
                        your account. This feature is linked to the so called "nomadic identity", a
                        feature unique to the federated Networks Hubzilla, Streams, Forte and the like.</li>
                </ul>   
                <hr/>
                <h2>FAQs</h2>
                <hr/>
                <h3>How to delete a card?</h3>
                <p>This is not possible. Proposal: Overwrite it.</p>
                <h3>How to switch the learn direction, wich side of the card is displayed?</h3>
                <p>Open the "Settings" of a box and look for the checkboxes under "How to learn".</p>
                <h3>How to backup a box?</h3>
                <p>
                    If you open the the box you wil find a download link somewhere above the button "Settings".
                </p>
                <p>
                    Make sure you downloaded the box as JSON file and not as HTML.
                </p>
                <p>
                    If you cloned your Hubzilla account... As soon as the cloud file (JSON representing a box) is changed/uploaded the cloud file
                    will be synchronized (sent) to your clones.
                </p>
                <h3>How to restore a box?</h3>
                <p>
                    The precondition is that you downloaded a box before. If you have done so, 
                    copy the JSON file into your cloud files <code>/cloud/nickname/flashcards/</code>
                </p>
                <h3>I deleted the cloud file representing a box. What to do?</h3>
                <p>
                    Open the addon. If your are lucky the box is still in the 
                    "local storage" of the browser. If yes the box is uploaded
                    to the server.
                </p>
            </div>
	</div>
</div>




<div id="panel_flashcards_cards" style="display: none;"></div>

<div id="flashcards_post_url" style="display: none;">{{$post_url}}</div>
<div id="flashcards_nick" style="display: none;">{{$nick}}</div>
<div id="owner_xchan_hash" style="display: none;">{{$owner_xchan_hash}}</div>
<div id="owner_xchan_addr" style="display: none;">{{$flashcards_owner}}</div>
<div id="flashcards_is_local_channel" style="display: none;">{{$is_local_channel}}</div>
<div id="has_write_permission" style="display: none;">{{$has_write_permission}}</div>
<!--
<p>
	<button class="btn" id="run_unit_tests"">Test</button>
</p>
-->



<!--
Modal to delete a box
-->
<div class="modal fade" id="delete_box_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="exampleModalLabel">Delete Box</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modal_body_delete_box">
                Are you sure to delete this box.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-danger" id="button_delete_box" boxid="notset">Delete</button>
            </div>
        </div>
    </div>
</div>
<div id="acl_modal_flashcards_cards"></div>


<script src="/addon/flashcards/view/js/flashcards.js"></script>

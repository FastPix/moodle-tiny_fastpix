@editor @editor_tiny @tiny @tiny_fastpix @javascript
Feature: Insert a FastPix shortcode via the TinyMCE picker
  In order to embed a FastPix video in any rich-text area
  As a teacher with the mod/fastpix:uploadmedia capability
  I need to click the "Insert FastPix video" toolbar button, pick a ready video
  from the modal and have {fastpix:pb_<id>} inserted at the cursor

  # Design notes
  # ============
  #
  # Capability context
  # ------------------
  # mod/fastpix:uploadmedia is defined at CONTEXT_COURSE (editingteacher /
  # manager archetypes — see mod/fastpix/db/access.php).  The button is gated on
  # it, so the editor surface used here MUST live in a course/module context.
  # The user-profile "Description" field (used by tiny_media's tests) is the
  # WRONG surface: it is a USER context where even an editing teacher does not
  # hold a course-level capability, so the button would be (correctly) absent.
  # We therefore drive the "Description" editor on a Page activity's settings
  # form, exactly as tiny_aiplacement/tests/behat/text.feature does for its own
  # course-level capability.
  #
  # Negative case
  # -------------
  # "Button absent" cannot be tested with a student — a student cannot open an
  # activity settings editor at all.  Following the tiny_aiplacement pattern we
  # give a second editing teacher a role with mod/fastpix:uploadmedia Prohibited
  # in the course: they can open the editor but must not see the button.
  #
  # End-to-end scope
  # ----------------
  # This suite owns the INSERT half (pick -> shortcode in the field -> persists
  # through a real form save).  The VIEW half (filter_fastpix turning the saved
  # shortcode into a player wrapper) is owned and tested by
  # filter/fastpix/tests/behat/embed_render.feature, so it is not duplicated
  # here.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | teacher2 | Tania     | Teacher  | teacher2@example.com |
    And the following "roles" exist:
      | name                 | shortname | archetype      |
      | Uploading teacher    | upteach   | editingteacher |
      | Non-uploading teacher | noupteach | editingteacher |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | noupteach      |
    And the following "permission overrides" exist:
      | capability               | permission | role      | contextlevel | reference |
      | mod/fastpix:uploadmedia  | Prohibit   | noupteach | Course       | C1        |
    And the following "activities" exist:
      | activity | name      | intro     | introformat | course | content     | contentformat | idnumber |
      | page     | PageName1 | PageDesc1 | 1           | C1     | PageContent | 1             | 1        |
    And the following "tiny_fastpix > assets" exist:
      | user     | course | playback_id | title      |
      | teacher1 | C1     | keepme01    | My lecture |

  # ---------------------------------------------------------------------------
  # Capability gate — an uploading teacher sees the button; a teacher whose
  # uploadmedia is Prohibited (but who can still open the editor) does not.
  # ---------------------------------------------------------------------------
  Scenario: The Insert FastPix video button is present for a capability holder
    When I am on the "PageName1" "page activity" page logged in as teacher1
    And I navigate to "Settings" in current page administration
    Then "Insert FastPix video" button should exist in the "Description" TinyMCE editor

  Scenario: The Insert FastPix video button is absent for a user without the capability
    When I am on the "PageName1" "page activity" page logged in as teacher2
    And I navigate to "Settings" in current page administration
    Then "Insert FastPix video" button should not exist in the "Description" TinyMCE editor

  # ---------------------------------------------------------------------------
  # Pick -> shortcode at cursor (phase-7.7 gate).  The modal lists "My lecture"
  # (seeded ready, public, owned by teacher1 so get_my_videos returns it) and
  # clicking the card inserts {fastpix:pb_keepme01} into the editor.
  # ---------------------------------------------------------------------------
  Scenario: Clicking a video card in the picker inserts the correct shortcode at the cursor
    Given I am on the "PageName1" "page activity" page logged in as teacher1
    And I navigate to "Settings" in current page administration
    When I click on the "Insert FastPix video" button for the "Description" TinyMCE editor
    Then "Insert FastPix video" "dialogue" should exist
    And I should see "My lecture" in the "Insert FastPix video" "dialogue"
    When I click on "My lecture" "button" in the "Insert FastPix video" "dialogue"
    # Match the shortcode anywhere in the serialised content — robust to the
    # <p> wrapper and whitespace TinyMCE adds around an inserted plain-text node.
    Then the field "Description" matches expression "/\{fastpix:pb_keepme01\}/"

  # ---------------------------------------------------------------------------
  # End-to-end (insert -> save -> persisted).  Proves the picker-inserted
  # shortcode survives a real form submission and is stored against the
  # activity.  The downstream render to a player wrapper is covered by
  # filter/fastpix/tests/behat/embed_render.feature.
  # ---------------------------------------------------------------------------
  Scenario: A picker-inserted shortcode persists through saving the activity
    Given I am on the "PageName1" "page activity" page logged in as teacher1
    And I navigate to "Settings" in current page administration
    When I click on the "Insert FastPix video" button for the "Description" TinyMCE editor
    And I click on "My lecture" "button" in the "Insert FastPix video" "dialogue"
    And I press "Save and display"
    And I navigate to "Settings" in current page administration
    Then the field "Description" matches expression "/\{fastpix:pb_keepme01\}/"

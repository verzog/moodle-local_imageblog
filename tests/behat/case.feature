@local @local_imageblog
Feature: Clinical case diagnosis, reveal and CPD
  In order to run continuing professional development on clinical cases
  As a clinician
  I need to submit diagnoses, have the author reveal the outcome and earn CPD

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | reader1  | Reanna    | Reader   | reader1@example.com |
    And the following config values are set as admin:
      | cpd_basehours        | 1                     | local_imageblog |
      | cpd_submit_factor    | 0.75                  | local_imageblog |
      | cpd_difficulty_scale | 0.5,0.75,1.0,1.25,1.5 | local_imageblog |
      | case_cpd_enabled     | 1                     | local_imageblog |

  Scenario: A reader diagnoses a case, the author reveals it and CPD is awarded
    # The author (admin) creates a published clinical case.
    Given I log in as "admin"
    And I visit "/local/imageblog/edit.php"
    And I set the field "Post type" to "Clinical case"
    And I set the field "Title" to "Pigmented lesion case"
    And I set the field "Post content" to "Asymmetric pigmented lesion on the back."
    And I set the field "Outcome / final diagnosis" to "Melanoma, confirmed on histopathology."
    And I set the field "Status" to "Published"
    And I press "Save changes"
    Then I should see "Pigmented lesion case"

    # The reader finds the published case and submits a diagnosis.
    When I log in as "reader1"
    And I visit "/local/imageblog/index.php"
    And I follow "Pigmented lesion case"
    Then I should see "Submit your diagnosis"
    When I set the field "Your diagnosis" to "Melanoma"
    And I press "Submit diagnosis"
    Then I should see "Your diagnosis has been recorded."

    # The author reveals the outcome to readers.
    When I log in as "admin"
    And I visit "/local/imageblog/index.php"
    And I follow "Pigmented lesion case"
    And I press "Reveal outcome to readers"
    Then I should see "Author's outcome"
    And I should see "Melanoma, confirmed on histopathology."

    # CPD is awarded by an adhoc task; once it runs the reader sees their hours.
    When I run all adhoc tasks
    And I log in as "reader1"
    And I visit "/local/imageblog/index.php"
    And I follow "Pigmented lesion case"
    Then I should see "You earned 0.75 CPD hours from this case."

  Scenario: No CPD is awarded while the kill-switch is disabled
    Given the following config values are set as admin:
      | case_cpd_enabled | 0 | local_imageblog |
    And I log in as "admin"
    And I visit "/local/imageblog/edit.php"
    And I set the field "Post type" to "Clinical case"
    And I set the field "Title" to "Switched-off case"
    And I set the field "Post content" to "A lesion to assess."
    And I set the field "Outcome / final diagnosis" to "Benign naevus."
    And I set the field "Status" to "Published"
    And I press "Save changes"
    When I log in as "reader1"
    And I visit "/local/imageblog/index.php"
    And I follow "Switched-off case"
    And I set the field "Your diagnosis" to "Naevus"
    And I press "Submit diagnosis"
    When I log in as "admin"
    And I visit "/local/imageblog/index.php"
    And I follow "Switched-off case"
    And I press "Reveal outcome to readers"
    And I run all adhoc tasks
    And I log in as "reader1"
    And I visit "/local/imageblog/index.php"
    And I follow "Switched-off case"
    Then I should not see "CPD hours from this case"

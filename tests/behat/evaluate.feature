@local @local_imageblog
Feature: Evaluate posts with an admin-defined rubric
  In order to run reader evaluations against a rubric
  As an administrator
  I need to define a rubric and attach it to an evaluate-type post

  Background:
    Given I log in as "admin"

  Scenario: An admin defines a rubric with a criterion
    When I navigate to "Plugins > Local plugins > Image blog > Manage evaluation rubrics" in site administration
    And I follow "Add rubric"
    And I set the field "Name" to "Review rubric"
    And I press "Save changes"
    Then I should see "Review rubric"
    When I follow "Add criterion"
    And I set the field "Name" to "Clarity"
    And I press "Save changes"
    Then I should see "Clarity"

  Scenario: An evaluate post shows its rubric and the reference editor to the author
    Given I navigate to "Plugins > Local plugins > Image blog > Manage evaluation rubrics" in site administration
    And I follow "Add rubric"
    And I set the field "Name" to "Review rubric"
    And I press "Save changes"
    And I follow "Add criterion"
    And I set the field "Name" to "Clarity"
    And I press "Save changes"
    When I navigate to "Plugins > Local plugins > Image blog > Blog posts" in site administration
    And I follow "New post"
    And I set the field "Post type" to "Tutorial"
    And I set the field "Evaluation rubric" to "Review rubric"
    And I set the field "Title" to "How to use the blog"
    And I set the field "Post content" to "A step-by-step tutorial."
    And I set the field "Status" to "Published"
    And I press "Save changes"
    Then I should see "How to use the blog"
    And I should see "Evaluate this post"
    And I should see "Clarity"

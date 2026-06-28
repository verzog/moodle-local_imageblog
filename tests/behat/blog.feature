@local @local_imageblog
Feature: Image blog administration and authoring
  In order to publish image-led blog posts
  As an administrator or blog author
  I need to reach the blog pages, manage taxonomy and create posts

  # Dates in these scenarios use Australian DD/MM/YYYY formatting per the
  # project locale guidance (none are currently date-driven).

  Background:
    Given I log in as "admin"

  Scenario: The settings page exposes the CPD and subscription options
    When I navigate to "Image blog > Settings" in site administration
    Then I should see "CPD hours for clinical cases"
    And I should see "Subscription emails"
    And I should see "Enable digest emails"

  Scenario: Seeded categories are listed on the category management page
    When I navigate to "Image blog > Manage categories" in site administration
    Then I should see "News"
    And I should see "Tutorial"
    And I should see "Case study"

  Scenario: The blog listing shows an empty state before any posts exist
    When I navigate to "Image blog > Blog posts" in site administration
    Then I should see "No posts found matching your filters."

  Scenario: The manage blog authors page reports no authors initially
    When I navigate to "Image blog > Manage blog authors" in site administration
    Then I should see "Manage blog authors"
    And I should see "No blog authors have been added yet."

  Scenario: An author can create a draft post and view it
    When I navigate to "Image blog > Blog posts" in site administration
    And I follow "New post"
    And I set the field "Title" to "Welcome to the image blog"
    And I set the field "Post content" to "This is the very first post body."
    And I press "Save changes"
    Then I should see "Welcome to the image blog"

  Scenario: A reader can reach the subscription page when digests are enabled
    Given the following config values are set as admin:
      | subscriptions_enabled | 1 | local_imageblog |
    When I navigate to "Image blog > Blog posts" in site administration
    And I follow "Email me new posts"
    Then I should see "Blog email subscription"

/* eslint-disable no-undef */
describe('Dashboard Downloads List page', () => 
{
  before(() => 
  {
    cy.visit('').then(()=>
    {
      cy.get('.navbarLinks',{timeout:60000}).contains('.nav-link','About').click()
    })
  })

  it('An external link icon is shown', () => 
  {
    
    "Reduce screen size to approximate a smartphone and see if the content displays correctly. 

Use the webguide homepage.
" Boxes and pillbox links reflow and resize, becoming full width.
"Print a published page.

Use the webguide home page or any long page with lots of images and boxes." Printed page should look moderately useful
Share dropdown is now available on all pages Share page with all four Social Media sites with no issues
Test shield - limited to advanced roles Publish a page.  Ask Web CMS Support to shield the page.  Check that the page is shielded. Shield a URL using directions at: http://www2.epa.gov/drupaltraining/forms/password-protection-external-review "You should be asked for a user id and password.  user id: epa-reviewer
password: Shield-2017*01"
System Editor Role 
 
 
As AU author, edit any content in their web area. All AUs can edit all content in their web area, EXCEPTING web form. Authors still cannot publish.
On a web area homepage, choose the group dashboard option.  Select the Browse links option.  Set options to Broken Links: yes, put 404 in the response code box.  Leave the other fields at their defaults. You get a list of broken links with 404 response codes in the list.
Perform a search using the main EPA search box. You can see the text you type in and search results seem relevant to your terms.
 
Publish page at a certain date and time You are able to do this
Pages are unpublished when sunset date is used You are able to do this
 You are able to create each option
 You are able to create an event with a map
 
 
Check this page: /node/148197/revisions/731523/view Page looks normal, particularly document dates after file attachments
Check this page: /node/63865/revisions/712209/view Page looks normal, particular "About the Data" box is full width




})

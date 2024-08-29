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
    In selected Web area, click Create Content and select Web Area Homepage
     Only system admins can create web area homepage, ask an admin to create a blank page for you
    Add Title Is required
    Remove banner Banner is removed
    Add banner Banner slides will open and user will have option to add title, text, and image
    Add banner title, text, image, remove image, add again, add alt text "Overlay will be visible when Banner image is added and saved.
     
    Images can be uploaded, removed, and replaced. image name is retained. For images, can use PNG, JPEG, GIF, JPG. 
    
    Alternative Text is Required
    
    Text alternative is visible in the source code."
    Add banner Slide The option to add another image will appear. If added the homepage banner will now be a slide show.
    Add text to Body field Chrome does not support paste with the wysiwyg editor - must use ctrl-v
    Save Should be prompted to fill in description in primary metadata
    Add Primary Metadata. Add more than 256 characters to the Description metadata. "User inputs well-formatted metadata into the following fields: Description, Keywords, Channel, Type.
    
    Description is now limited to 256 characters. If you edit an existing page, and the description is more than 256 characters, you will be prompted to reduce the number. Keywords are no longer required.
    
    Metadata output into HTML source on publication."
    Save again  "Content is visible, styles are rendered correctly and look good in Google Chrome and OK in IE 8.
    
    Layout options will be available"
    Select the Layout tab Layout options will be available
    Add section Will be prompted to choose layout and add an administration label (not required)
    Add Block > Custom block > Paragraph > Add Paragraph Type Box "Title of the block is required - Display title can be checked or unchecked. If unchecked title will not show when you view the page.
    
    Can add a box title and a header image. Can Add HTML to the box content.
    
    Can add footer link
    
    Can select Box Style"
    Save layout All changes will appear in the view tab
    Search the Homepage by filtering Filter content on the Web Areas Dash board using the "content type" filter
    Select edit then select the layout tab Can edit different fields by selecting on the pencil icon, after clicking on the pencil icon, select the configure option
    Select the pencil icon > Move Will be given the option to move the blocks to different regions on the page
    Save the page by selecting the "Save layout" button Page is saved and you're directed to view page.
    View page Content (except content in aside block) is visible, styles are rendered correctly and look good in Google Chrome and OK in IE
    Publish Prompted to enter log message for state change and verify Section 508 compliance. Page is published.  Log message is visible in revisions tab.  State/status on View tab, Workflow tab, and Dashboard > Content tab has changed to published. 
    View Revisions Tab "All revisions are listed in reverse chronological order. 
    
    Uses TC - 3: steps 2-6 to test revisions of this page"
    Review source code of published page. Note, you must be looking at the published revision, meaning the URL is the alias. All filled out metadata is available in source, including all date metadata (reviewed, modified, created). Date metadata matches type metadata.
    "Use the clone tab to clone a page.
    
    You can only clone the current revision." All content (page content, panelizer content, metadata, etc.) is present in the cloned page.
    Go to content tab on the web area group dashboard and select Latest Revisions Cloned page appears in list on Content > Latest Revisions tab
    Select your page and then one of the options from the Actions pull-down menu. (e.g. Set to Published) Page changed moderation state (e.g. published) depending on what action you selected. 
    
})

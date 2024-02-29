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
    In Perspectives Web area, click Create Content and select Perspective Create Perspective screen opens.  Web area field defaults to current selection
Add title is required, has red *
Add Release Date is required, has red * Enter MM/DD/YYYY
Add content to Body, by direct input or copy-and-paste is required, has red *  Chrome does not support paste with the wysiwyg editor - must use ctrl-v.
Add content to the Body field,    is required, has red *
Add each paragrah type (from Add HTML dropdown) to the body section: link list, Box, Header, Dynamic List, Slideshow, from library (reusable paragraph), Card Group not required
Add Subjects  "is required, has red * Select from dropdown menu: Adminsitrator, Agriculture, Air, Award and Recognition, Climate, Science matters.
This will appear as a link in the green box Related Info on the right-hand side of the page.
""Read other EPA Perspectives about [insert subject]"""
In Subjects: Add another item  not required
Add Publishers not required
Add another item not required - can add a second Publisher 
Add Author > Click Add existing Author, type in your name Add name Rudy, name is not in the system.
Click Add new Author Choose Add new Author, fields open to add Name, Image, Title/Position, Office, Biography
Fill in Author content "Name * required
Image - not required
If you add image, Alternative text required
Click Create Author button
Author field populates.
To remove author, or edit, click Edit Button, then click Edit to edit, or Remove to remove."
Title/Position, Office, Biography fields "

Title/Position, Office field are limited to 240 characters.
Allowed HTML tags: <@ accesskey class data-date dir id lang style tabindex title name data> <a href hreflang occurrence id name target rel> <br> <span> <em> <i> <strong> <b> <small> <s> <sub> <sup> <mark> <abbr !title> <q cite> <cite> <ins> <del cite datetime> <time datetime pubdate> <var> <samp> <code> <details open> <summary> <label for form> <input> <textarea> <meter min max low high optimum form value> <select> <optgroup disabled label> <option value> <output for form name> <button> <datalist> <keygen autofocus challenge disabled form keytype name> <progress max value> <img alt crossorigin height longdesc !src width usemap> <embed height src type width> <object data form height name type usemap width> <param name value> <source media !src type> <track default kind label src srclang> <map name> <area alt coords href hreflang media rel shape target type>
Lines and paragraphs break automatically.
After entering info, click Add Author"
Teaser Image: click add media button Select an image from media or upload new image. This image will only display on the perspectives search results page; it will not display on this perspective page.
Primary Metadata - add Description and  keyword, Channel, Geographic Locations, Environmental Laws, Regulations & Treaties. "User inputs well-formatted metadata into the following fields: Description, Keywords, Channel

Description is now limited to 256 characters. If you edit an existing page, and the description is more than 256 characters, you will be prompted to reduce the number. The Description text will appear in the Teaser. Keywords are no longer required.

Metadata output into HTML source on publication."
Save "Click Save takes you to saved page in view tab.
The Related Information Green box will not have the Subjects populated until after publishing."
Publish Prompted to enter log message for state change and verify Section 508 compliance. Page is published.  Log message is visible in Revisions tab.  State/status on View tab, Revisions tab, and Dashboard > Content tab has changed to published. Current revision is "published," all other revisions are draft. 
View teaser https://stage-www.epa.gov/node/271297/latest This is a dynamic list of teasers, your new Perspective shows up on the list.
  
    

})

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
    
    
    1 Create and publish new node, filling out all fields, particularly the "type" metadata. "When viewing source of published page, you see metadata for dc.date.reviewed. Date is appropriate for type (90, 365, 730 days into future).
" 
2 "Manipulate review date (in database) with help of admin, to within 6 weeks from today's date.
Or you can manually put content into ""Published, Needs Review"" state." "Node moved into ""published, needs review"" state.
Email recipients: Revision author, Email address entered in ""Editor email address"" field on the group node
Email subject: EPA Content Needs Review > [web area name] > [page title]" 
3 Manipulate review date (in DB) with help of admin, to within 3 weeks from today's date. "Email recipients: Revision author, Users in group with Editor role, Users in group with Administrator Member role, Email address entered in ""Editor email address"" field on the group node
Email subject: EPA Content Needs Review > [web area name] > [page title]" 
4 Manipulate review date (in DB) with help of admin, to within 1 week from today's date. "Node moved into ""published, scheduled for expiration"" state.
Email recipients: Users in group with Editor role, Users in group with Administrator Member role, Email address entered in ""Editor email address"" field on the group node
Email subject: EPA Content About to Expire > [web area name] > [page title]

Content managers see the content in the ""Pending Actions In Your Web Areas: Expiring, Please Review Soon!"" blue block
-Note: you may have to refresh

Author sees the content in the ""Pending Actions: Expiring, Please Review Soon!"" green block
-Note: you may have to refresh" 
5 Manipulate review date (in DB) with help of admin, to within 1 day from today's date. "Email recipients: Users in group with Editor role, Users in group with Administrator Member role, Email address entered in ""Editor email address"" field on the group node
Email subject: EPA Content Will Expire Tomorrow > [web area name] > [page title]" 
6 Manipulate review date (in DB) with help of admin, to today's date. Or manually expire the content. "Node moved into ""unpublished"" state.

Email sent *only* if review date is reached (manipulated).
Email recipients: Users in group with Editor role, Users in group with Administrator Member role, Email address entered in ""Editor email address"" field on the group node
Email subject: EPA Content Has Expired > [web area name] > [page title]

Once unpublished, there's no review date." 
7 Re-edit content in "review" state and republish. dc.date.reviewed is moved out into future date appropriate for type (90, 365, 1095 days). 
8 Create and publish new webform dc.date.reviewed is present in the metadata 
9 Create and save new page, filling out all fields, particularly the "type" metadata.  Send for review "Revision enters ""Draft, needs review"" state
Email recipient: Users in group with Approver role
Email subject: EPA Content Needs Approval > [web area name] > [page title]

After creating page, the current state is draft. Available action is send for review. Prompted to submit log message for state change. 

After sending for review, current state is: draft, needs review. No available actions for author.

Content managers see the content in the ""Pending Actions In Your Web Areas: Needs Approval"" blue block
-Note: you may have to refresh
Author see nothing different. The page in question may be listed under My Recent Edits." 
10 Approver returns saved draft to author. """Return to author"" transition is fired. Node is back in draft state.
Email recipient: Node author
Email subject: EPA Draft Approval Denied > [web area name] > [page title]

On View tab, current state is: draft, needs review.  Available actions are: approve and leave unpublished; return to author; save for later (keep unpublished); and publish now.  

Authors can only edit.

On return to author, approver is prompted to enter log message for state change.   Current state is: Draft.  Available actions are: Send for review; Save for later (keep unpublished); Publish now.

Authors can edit or send for review." 
11 Approve, leave unpublished a saved draft that's in "Draft, Needs Review" state. "Revision enters ""Draft, approved"" state
Email recipients: Users in group with Editor role, Users in group with Administrator Member role
Email subject: EPA Content Has Been Approved > [web area name] > [page title]

Content managers see the content in the ""Pending Actions In Your Web Areas: Approved, But Unpublished"" blue block
-Note: you may have to refresh

Author see nothing different. The page in question may be listed under My Recent Edits.

Before sending for review, Author's only tasks are edit and send for review. Upon sending for review, author is prompted for log message.

After sending for review, author's only possible task is to edit the page.

For approver, on View tab, available actions are: approve and leave unpublished; return to author; save for later (keep unpublished); and publish now." 
   






    cy.contains('a', 'Downloads').find('.fa-external-link-alt').should('exist')
  })
})

---
el: .usa-table
title: Table
state: inreview
---
See [https://designsystem.digital.gov/components/table/]() and
[https://components.designsystem.digital.gov/components/detail/tables.html]().

__Variables:__
* modifier_classes: [string] Optional additional CSS classes.
* caption: [string] Table caption.
* header: [array] Header cells. Each item is an object containing:
  * attributes: [string] HTML attributes of the cell.
  * content: [string] Content of the cell.
* footer: [array] Footer cells. Each item is an object containing:
  * attributes: [string] HTML attributes of the cell.
  * content: [string] Content of the cell.
* rows: [array] Table rows. Each item is an object containing:
  * attributes: [string] HTML attributes of the row.
  * cells: [array] Table cells. Each item is an object containing:
    * tag: [string] HTML tag that wraps the cell. (`th` or `td`)
    * attributes: [string] HTML attributes of the cell.
    * content: [string] Content of the cell.

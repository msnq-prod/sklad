# Location Management

This guide explains how the location directory works in AdamRMS, including the recursive
hierarchy, filtering behaviour, file handling, and the most common workflows for operations
teams. All references below correspond to the implementation in
`src/location/index.php`.

## Location Hierarchy and Recursion

Locations are stored with an optional `locations_subOf` parent reference. When the Locations
page loads without archive or search filters, the controller retrieves only top-level
locations and then calls the `linkedLocations()` helper to recursively load sublocations
linked to each root location.

- **Top-level fetch** – Records with a `NULL` `locations_subOf` field are fetched when the
  request is not filtering by archive or search. They are ordered alphabetically and added
  to the `$PAGEDATA['locations']` array as roots.
- **Recursive expansion** – `linkedLocations($locationId, $tier, $locationKey)` queries for
  children of a given location, increases the depth counter, and appends each child both to
  the global `$PAGEDATA['allLocations']` list and to the parent’s `linkedToThis` collection.
  The function calls itself for each child, creating an arbitrarily deep tree of
  sublocations.
- **Tier metadata** – Each nested record stores a `tier` attribute representing the depth
  of recursion so Twig templates can indent or otherwise format the hierarchy.
- **File aggregation** – Every location, including nested ones, receives a `files` array
  populated from `s3List(11, $locationId)`. This allows the UI to surface attached files for
  both root locations and their sublocations.

When a search query or archive view is active, the recursion is skipped so that results can
be presented as a flat list matching the filter criteria.

## Filtering and Search Controls

The controller supports several filters that can be combined through query parameters:

- **Client filter** – `?client=<clients_id>` constrains results to a single client by adding
  a `locations.clients_id` `WHERE` clause. Client metadata is joined for display via a LEFT
  join on the `clients` table.
- **Archive toggle** – `?archive=1` switches the listing into archive mode. The query then
  selects locations with `locations_archived = 1` without the `locations_subOf IS NULL`
  constraint so archived sublocations are visible. Archived views do not call
  `linkedLocations()`.
- **Search** – `?q=...` sanitizes the term and builds a composite `WHERE` covering name,
  address, and notes. Search results are paginated and displayed without hierarchical
  nesting to emphasise relevance.
- **Pagination** – The standard `page` and `pageLimit` controls are passed to `paginate()`
  on the `locations` table.
- **File drill-down** – `?files=1&id=<locations_id>` re-renders the page using the
  `location/location_files.twig` template and shows the S3 file list (`s3List`) for the
  selected location, including any nested folder structures returned by S3.

The controller also loads the active client catalogue (`clients_deleted = 0`,
`clients_archived = 0`) for use in filters and creation forms.

## Key Workflows

### Creating a Location
1. Navigate to **Locations** and ensure you have the `LOCATIONS:VIEW` permission.
2. Use the **Add Location** action (UI button) to open the creation form.
3. Provide the name, address, and optional notes. Choose a client relationship if the
   location belongs to a specific customer.
4. To create a sublocation, select the parent location in the “Sub of” dropdown. The new
   record will appear under its parent once saved.
5. Upload supporting documents or photos; they are stored against the location via
   `s3List` and can be accessed from the files view.

### Working with Archived Locations
1. Toggle the **Archive** filter (`?archive=1`) to display archived records.
2. Archived results show both root and child locations in a flat list for quick review.
3. Use the restore or delete controls exposed in the UI to manage archived entries.
4. Exit archive mode to return to the active hierarchy (`?archive` removed).

### Exporting or Downloading Files
1. From the Locations list, open the files view for a location by selecting **Files** or
   navigating to `?files=1&id=<locations_id>`.
2. The controller populates `$PAGEDATA['LOCATION']['files']` with the output of
   `$bCMS->s3List(11, $locationId)`, which enumerates folders and objects stored under the
   location’s namespace in S3.
3. Use the UI actions to download, share, or remove files. Nested folders displayed in the
   S3 listing mirror the structure maintained in the bucket.

### Linking Locations to Clients and Projects
1. Assign a client when creating or editing a location to ensure it appears in
   client-filtered views. The client selector uses the list generated from the `clients`
   table where entries are active and belong to the current instance.
2. For project associations, use the project linkage controls available in project detail
   pages to reference a location. Locations already tied to a client can still be linked to
   multiple projects as needed.
3. When filtering by client (`?client=<id>`), only locations (and their sublocations) with
   the selected client relationship are returned, keeping project assignment lists concise.

### Typical User Scenario
The following narrative illustrates how operations staff usually engage with the Locations
module during a workday:

1. **Morning checks** – The duty manager logs in, opens the Locations page, and reviews the
   default hierarchical view to confirm that overnight updates have been filed correctly for
   each warehouse and sublocation.
2. **Client-specific preparation** – Before a client visit, they apply the client filter to
   ensure the spaces assigned to that customer are accurate and up to date, making any quick
   edits as needed.
3. **On-site adjustments** – While touring the facility, the manager uses the sublocation
   dropdown when creating new rooms or zones discovered during the walkthrough, immediately
   attaching photos and documents through the files panel.
4. **Project coordination** – Back at their desk, they open the relevant project page to link
   the newly added or updated locations so the project team has the latest information.
5. **End-of-day housekeeping** – Finally, they switch to archive mode to review old or
   inactive locations, restoring or purging entries to keep the structure clean for the next
   shift.

## Best Practices

- **Plan the hierarchy** – Define top-level sites (warehouses, venues) first and then add
  sublocations for rooms, zones, or storage areas. Avoid creating deep chains unless they
  reflect actual operational structure.
- **Use consistent naming** – Prefix sublocations with meaningful identifiers (e.g.,
  `Warehouse A / Bay 3`) so that search and flat listings remain intuitive.
- **Audit access** – Ensure only users with the appropriate instance permissions can view
  or modify locations. Use role-based permissions to restrict archive restoration and file
  downloads where necessary.
- **Client segregation** – Always assign the correct client to a location to enable precise
  filtering and to keep client-specific data isolated.
- **Review archives periodically** – Regularly check archived locations to remove obsolete
  entries or restore active ones, keeping the hierarchy clean.
- **Secure file storage** – Follow organisational policies for S3 bucket access, enforce
  least privilege in IAM roles, and periodically prune sensitive documents from archived
  locations.

## Plain-Language Quick Guide

If you just need the basics, follow these simple steps to work with locations day to day:

1. **Open the Locations page** – You will see big locations (warehouses, venues) first. Any
   smaller areas are shown underneath when you expand them.
2. **Add or edit a place** – Click the add button to create a new location, or open an
   existing one to change it. Choose a parent if it sits inside another area, and pick the
   client it belongs to so filters stay accurate.
3. **Attach files** – Use the files tab to drop in photos, floor plans, or documents. They
   live in S3 automatically, and you can grab them later from the same tab.
4. **Filter when you need focus** – Use the client dropdown, search box, or archive toggle to
   shrink the list to only what matters right now.
5. **Keep archives tidy** – When something closes or moves, archive it instead of deleting.
   Check the archive view occasionally to restore or permanently clear old entries.
6. **Link to projects** – On project pages, link the relevant locations so everyone sees the
   same information while planning work.

That’s it: create locations, add sublocations and files as you go, filter to find what you
need, and tidy the archive so the structure stays clean for the whole team.


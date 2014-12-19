ImportWXR
=========

[Bolt Extension] An Import filter for WXR files, as created by Wordpress or PivotX



----

Example of contenttypes.yml, if importing from Wordpress:


attachments:
    name: Attachments
    singular_name: Attachment
    fields:
        title:
            type: text
            class: large
        slug:
            type: slug
            uses: title
        image:
            type: image
        body:
            type: html
            height: 300px
        url:
            type: text
            variant: inline

  #     attachment_url: url
  #     status: status
  #     post_parent: parent
  #     post_parent_contenttype: "entries"

pages:
    name: Pages
    singular_name: Page
    fields:
        title:
            type: text
            class: large
            group: content
        slug:
            type: slug
            uses: title
        image:
            type: image
        teaser:
            type: html
            height: 150px
        body:
            type: html
            height: 300px
        template:
            type: templateselect
            filter: '*.twig'
    taxonomy: [ chapters ]
    recordsperpage: 100


entries:
    name: Entries
    singular_name: Entry
    fields:
        title:
            type: text
            class: large
            group: content
        slug:
            type: slug
            uses: title
        teaser:
            type: html
            height: 150px
        body:
            type: html
            height: 300px
        image:
            type: image
            group: media
        video:
            type: video
    relations:
        pages:
          multiple: false
          order: title
          label: Select a page
    taxonomy: [ categories, tags ]
    record_template: entry.twig
    listing_template: listing.twig
    listing_records: 10
    default_status: publish
    sort: -datepublish
    recordsperpage: 10
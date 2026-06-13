# BookStack-meilisearch

This is a concept provement testing. Use Meilisearch as BookStack's search suggestion.

**NOTE**: not all funtionality has been implemented yet. Do NOT use it on production.

----

# Why Meilisearch

BookStack use space to separate sentence to words (search tokens). But not all language can be index by this algorithm.

Meilisearch use N-grams to create indexes. It make better search experience, and can use on most of languages.

----

BookStack is an opinionated documentation platform that provides a pleasant and simple out-of-the-box experience. New users to an instance should find the experience intuitive and only basic word-processing skills should be required to get involved in creating content on BookStack. The platform should provide advanced power features to those that desire it, but they should not interfere with the core simple user experience.

# Migration

**WARNING**: BACKUP, before you do any changes.

You can manually copy and overwrite `app/Search/SearchController.php` on your currrent project.

set your Meilisearch configs in `.env`:

```
MEILISEARCH_HOST=127.0.0.1
MEILISEARCH_PORT=7700
MEILISEARCH_MASTER_KEY=
```

Then execute `php artisan bookstack:regenerate-search` to re-index data by Meilisearch.

if no error occurs, you can search using Meiliseach on SearchSuggestion (only, see sreenshot below). 

----

# Screenshot

You can now search using event words in the middle of the phrease:

<img width="908" height="540" alt="2026-06-13-17:13:53-001" src="https://github.com/user-attachments/assets/04004344-3865-464b-9de0-8e953d69c1c0" />


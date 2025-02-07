# EPA Elasticsearch

This module's purpose is to listen for the Elasticsearch's "build parameters" event where we add some custom functionality to boost the score for the news_release content type based on how close the News Release's "Release Date" (field_release) is to now.

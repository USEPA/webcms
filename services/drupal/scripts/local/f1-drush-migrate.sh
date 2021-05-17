#!/bin/bash

# Note: First use allow-unprocessed.sh to set allowable unprocessed counts
# before usage. These are stored in the D8 DB.

set -euo pipefail

# Number of items to process in a single batch run
batch_size=1000

# https://stackoverflow.com/a/33248547/3779
trim() {
  local s2 s="$*"
  until s2="${s#[[:space:]]}"; [ "$s2" = "$s" ]; do s="$s2"; done
  until s2="${s%[[:space:]]}"; [ "$s2" = "$s" ]; do s="$s2"; done
  echo "$s"
}

# USAGE:
#   run_migration MIGRATION
#
# MIGRATION: The machine name of a migration to run.
#
# This function runs a migration to completion, using the configured $batch_size variable
# above. If a migration yields unprocessed entities greater than the threshold specified
# by the allow-unprocessed.sh script, then this function returns a non-zero exit code.
run_migration() {
  local migration
  local total batches unprocessed remaining
  local start finish time
  local i
  local halted

  # Get the migration name
  migration="$1"

  # If already done, skip.
  unprocessed="$(trim $(f1 drush ms "$migration" --field=unprocessed))"
  if test "$unprocessed" = 0; then
    echo "[$migration] Bypassing, 0 remaining"
    return 0
  fi

  # Determine the number of entities to process, and divide that by the batch size.
  batches=$((unprocessed / batch_size))

  # Add an extra batch if the unprocessed entities isn't a clean multiple of the batch size
  # (bash doesn't use floating point, so we can't use a rounding function here).
  if (((unprocessed % batch_size) != 0)); then
    ((batches += 1))
  fi

  echo "[$migration] Importing $unprocessed entities"
  start="$(date +%s)"

  # Run the migration
  for ((i = 0; i < batches; i++)); do
    echo "[$migration] Importing ($((i + 1))/$batches batches)"
    f1 drush mim \
      --limit=$batch_size \
      --force \
      "$migration"

    # Check if we've asked to halt. Migration's own status field isn't really reliable
    # for discerning whether a migration has been stopped or completed.
    halted="$(trim $(f1 drush state-get epa.migrations_halted))"
    if test -n "$halted"; then
      # Output the num remaining and exit
      remaining="$(trim $(f1 drush ms "$migration" --field=unprocessed))"
      echo "[$migration] Halted ($remaining unprocessed of $unprocessed)"
      echo "Halting migration script, deleting halt signal"
      f1 drush state-del epa.migrations_halted
      exit 1
    fi
  done

  # Calculate the difference in seconds for some timing statistics
  finish="$(date +%s)"
  time=$((finish - start))

  # Determine how many unprocessed items were left behind by the migration.
  remaining="$(trim $(f1 drush ms "$migration" --field=unprocessed))"
  total="$(trim $(f1 drush ms "$migration" --field=total))"

  # If we don't have any special expected count, default to zero.
  expected="$(trim $(f1 drush state-get epa.allowed_unprocessed.$migration))"
  expected="${expected:-0}"

  # If the unprocessed count is above the threshold, return a failure.
  if test "$remaining" -gt "$expected"; then
    echo "[$migration] Encountered $remaining unprocessed items; expecting $expected" >&2
    return 1
  fi

  echo "[$migration] Done ($unprocessed in ${time}s in this batch, total $total)"
}

# Usage:
#   run_migration_group NAME MIGRATION [MIGRATION...]
#
# NAME: A human-friendly name for this group of migrations.
# MIGRATION: One or more migration machine names (see run_migration above).
#
# This function runs a batch of migrations in a group. It serves mostly to provide visual
# and logical separation of migration groups rather than perform any logic on its own.
# If any migration in the list fails, processing stops immediately and this function returns
# the error code.
run_migration_group() {
  # Fetch the group name and then shift the $@ array. This allows us to loop over $@
  # directly to run each migration in this group.
  local group_name="$1"
  shift

  echo "Running migration group: $group_name"

  for migration in "$@"; do
    if ! run_migration "$migration"; then
      # Propagate failure to the caller of this function. Since we're running this script
      # under set -e, this will fail the entire script and stop migration immediately,
      # rather than continuing to process entities and fail due to a lack of missing
      # dependencies.
      echo "Failed to run migration $migration" >&2
      return 1
    fi
  done
}

# Arrays here are lists of migrations to be run, roughly organized by migration group.

taxonomy_term_migrations=(
  upgrade_d7_taxonomy_term_channels
  upgrade_d7_taxonomy_term_environmental_laws_regulations_and_treaties
  upgrade_d7_taxonomy_term_epa_organization
  upgrade_d7_taxonomy_term_event_type
  upgrade_d7_taxonomy_term_faq_topics
  upgrade_d7_taxonomy_term_geographic_locations
  upgrade_d7_taxonomy_term_press_office
  upgrade_d7_taxonomy_term_program_or_statute
  upgrade_d7_taxonomy_term_subject
  upgrade_d7_taxonomy_term_type
  upgrade_d7_taxonomy_term_type_of_proposed_action
)

group_migrations=(
  upgrade_d7_group_web_area
)

user_migrations=(
  upgrade_d7_user
)

file_migrations=(
  upgrade_d7_file
)

media_entity_migrations=(
  upgrade_d7_file_entity_audio
  upgrade_d7_file_entity_document
  upgrade_d7_file_entity_image
  upgrade_d7_file_entity_other
  upgrade_d7_file_entity_video
)

document_migrations=(
  upgrade_d7_node_document
  upgrade_d7_node_document_panelizer
  upgrade_d7_node_revision_document
  upgrade_d7_node_revision_document_panelizer
)

paragraph_migrations=(
  upgrade_d7_paragraph_applicants_or_respondents
  upgrade_d7_paragraph_cfr
  upgrade_d7_paragraph_docket
  upgrade_d7_paragraph_frc
  upgrade_d7_paragraph_legal_authorities
  upgrade_d7_paragraph_locations_of_prop_actions
  upgrade_d7_paragraph_press_officers
  upgrade_d7_node_web_area_paragraph_banner_slide
  upgrade_d7_node_web_area_paragraph_banner
  upgrade_d7_node_revision_web_area_paragraph_banner
  upgrade_d7_node_news_release_paragraph_html
  upgrade_d7_node_revision_news_release_paragraph_html
  upgrade_d7_node_webform_paragraph_html
  upgrade_d7_node_revision_webform_paragraph_html
)

node_migrations=(
  upgrade_d7_node_web_area
  upgrade_d7_node_web_area_panelizer
  upgrade_d7_node_event
  upgrade_d7_node_faq
  upgrade_d7_node_news_release
  upgrade_d7_node_page
  upgrade_d7_node_page_panelizer
  upgrade_d7_node_page_sixpack
  upgrade_d7_node_public_notice
  upgrade_d7_node_regulation
  upgrade_d7_node_webform
)

node_revision_migrations=(
  upgrade_d7_node_revision_web_area
  upgrade_d7_node_revision_web_area_panelizer
  upgrade_d7_node_revision_event
  upgrade_d7_node_revision_faq
  upgrade_d7_node_revision_news_release
  upgrade_d7_node_revision_page
  upgrade_d7_node_revision_page_panelizer
  upgrade_d7_node_revision_page_sixpack
  upgrade_d7_node_revision_public_notice
  upgrade_d7_node_revision_regulation
  upgrade_d7_node_revision_webform
)

webform_migrations=(
  upgrade_d7_webform
  upgrade_d7_webform_submission
)

group_content_migrations=(
  upgrade_d7_group_content_file
  upgrade_d7_group_content_node_document
  upgrade_d7_group_content_node_event
  upgrade_d7_group_content_node_faq
  upgrade_d7_group_content_node_news_release
  upgrade_d7_group_content_node_page
  upgrade_d7_group_content_node_public_notice
  upgrade_d7_group_content_node_regulation
  upgrade_d7_group_content_node_webform
  upgrade_d7_group_content_node_web_area
  upgrade_d7_group_menu_links
)

latest_revision_migrations=(
  upgrade_d7_node_latest_revision
)

path_redirect_migrations=(
  upgrade_d7_path_redirect
)

# Import taxonomy terms, groups, and users.
run_migration_group "Taxonomy Terms" "${taxonomy_term_migrations[@]}"
run_migration_group "Groups" "${group_migrations[@]}"
run_migration_group "Users" "${user_migrations[@]}"

# Re-run the groups migration to associate users with groups. Since this requires the
# --upgrade flag, we run the migration directly instead of through the run_migration
# helper function.
echo "Re-running groups migration"
f1 drush mim --update "${group_migrations[0]}"

# Run file migrations
run_migration_group "Files" "${file_migrations[@]}"

# Refresh the s3fs cache. This is commented out because I can't seem to get it to work in minio.
echo "Refreshing s3fs"
# f1 drush s3fs-refresh-cache

# Run the rest of the migrations

run_migration_group "Media Entities" "${media_entity_migrations[@]}"
run_migration_group "Documents" "${document_migrations[@]}"
run_migration_group "Paragraphs" "${paragraph_migrations[@]}"
run_migration_group "Nodes" "${node_migrations[@]}"
run_migration_group "Node Revisions" "${node_revision_migrations[@]}"
run_migration_group "Webforms" "${webform_migrations[@]}"
run_migration_group "Group Content" "${group_content_migrations[@]}"
run_migration_group "Set Latest Revision" "${latest_revision_migrations[@]}"
run_migration_group "Path Redirects" "${path_redirect_migrations[@]}"

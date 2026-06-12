# Page evaluation checks — replication spec

This document describes every check performed during per-page SEO suggestion evaluation in SEO Booster. It is intended for another project that needs to replicate the same analysis behavior.

**Source of truth:** All per-page checks are implemented in `inc/SEO_Analysis.php`. The main entry is `analyze($use_full_page, $force_download)`, which runs a fixed list of check methods.

---

## 1. Overview

**Page evaluation** is run once per URL (or per post/term). It produces:

- **Issues** — problems that should be fixed (errors and warnings).
- **Improvements** — optional suggestions (low priority).
- **Good** — positive findings (good practices).
- **Score** — a 0–100 value derived from the above.

**Content modes:**

- **Without full page:** Analysis uses post/term content (and optionally stored SEO meta from integrated plugins). No live HTML is fetched.
- **With full page:** The URL is fetched (or loaded from cache), and the full HTML is used. Additional checks (link validation, meta tags, structured data, etc.) run only in this mode.

When full page content is available, title and meta description are preferred from the live HTML (`<title>`, `<meta name="description">`); otherwise they come from the SEO plugin meta.

---

## 2. Severity and result types

| Type         | Severity in code     | Stored in DB (issues table) | Description |
| ------------ | -------------------- | ---------------------------- | ----------- |
| **Issues**   | `error`, `warning`    | `critical` / `high` / `medium` (via `map_severity`) | Problems to fix |
| **Improvements** | (treated as low) | `low`                        | Optional improvements |
| **Good**     | —                    | `good`                       | Positive findings |

**Severity mapping when saving** (`SEO_Issues_Manager::map_severity`):

- `error` → `critical`
- `warning` → `high`
- `improvement` → `medium`
- Good items are stored with severity `good` (not counted as “issues” for totals).

Each result has:

- **key** — Unique string identifier (e.g. `title_missing`, `gsc_low_ctr_good_position`).
- **message** — User-facing text.
- **extra_data** — Optional object (e.g. list of URLs, keyword lists, line numbers). Omitted if not used.

---

## 3. Score calculation (per-page)

Used in `SEO_Analysis::calculate_score()`:

- Start at **100**.
- **Errors:** −5% per error, **cap 50%** total.
- **Warnings:** −2.5% per warning, **cap 30%** total.
- **Improvements:** −1% per improvement, **cap 15%** total.
- **Good:** +0.5% per good, **cap 10%** bonus.

Formula:

```text
error_deduction     = min(50, error_count * 5)
warning_deduction   = min(30, warning_count * 2.5)
improvement_deduction = min(15, improvement_count * 1)
good_bonus          = min(10, good_count * 0.5)
score               = max(0, min(100, round(100 - error_deduction - warning_deduction - improvement_deduction + good_bonus)))
```

---

## 4. Data inputs

| Input      | Source | Notes |
| ---------- | ------ | ----- |
| **Title**  | Live `<title>` from full page (if available), else SEO plugin meta, else post title | — |
| **Meta description** | Live `<meta name="description" content="...">` from full page (if available), else SEO plugin meta, else Yoast `_yoast_wpseo_metadesc` | — |
| **Focus keyword** | Active SEO plugin via `Google_API::get_focus_keywords(object_id)`; first keyword used | — |
| **Noindex** | `seo_data['noindex']` from SEO plugin | — |
| **Content** | `get_analysis_content()`: full page HTML if present, else post body (or term description) | For content length, post content is rendered with `apply_filters('the_content', ...)` for posts |
| **GSC**    | URL Inspection API; keyword/query data from `sb2_query_keywords` and `sb2_query_keywords_history` | Only when GSC is connected and site URL is set |

---

## 5. Checks (run order)

Checks are executed in a fixed order. Some run only when **full page content** is available.

### 5.1 Always run (no full page required)

#### Title (`check_title`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `title_missing` | error | No title (empty after fallbacks: live `<title>`, SEO meta, post title). |
| `title_no_keyword` | warning | Focus keyword set and not in title (case-insensitive). |
| `title_has_keyword` | good | Focus keyword set and present in title. |

#### Meta description (`check_meta_description`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `description_missing` | error | No meta description (live meta, SEO meta, Yoast meta all empty). |
| `description_no_keyword` | warning | Focus keyword set and not in description. |
| `description_has_keyword` | good | Focus keyword set and in description. |

#### Focus keyword (`check_focus_keyword`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `keyword_missing` | low (improvement) | No focus keyword set. |
| `keyword_set` | good | Focus keyword is set. |

#### Noindex (`check_noindex_status`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `noindex_set` | improvement | Page is noindex. |
| `indexable` | good | Page is indexable. |

#### Content length (`check_content_length`)

- **Word count:** From rendered post content (`apply_filters('the_content', ...)`) for posts, else `get_analysis_content()` stripped of tags.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `content_too_short` | warning | Word count < 300. |
| `content_long` | improvement | Word count > 3000. |
| `content_length` | good | 300 ≤ word count ≤ 3000. |

#### Heading structure (`check_heading_structure`)

- H1/H2 are counted in `get_analysis_content()` (full page or post/term). For posts, if no H1 in content, post title can be treated as H1.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_h1` | warning | Zero H1 in content (and no post-title-as-H1). |
| `multiple_h1` | warning | More than one H1. |
| `h1_ok` | good | Exactly one H1 (text reported in message). |
| `no_h2` | improvement | Zero H2. |
| `has_h2_headings` | good | At least one H2 (count in message). |

#### Image alt text (`check_image_alt_text`)

- Count images in content. Exclude “hidden” elements (e.g. via `is_hidden_element()`). For each image: missing `alt` attribute vs empty `alt=""`.
- **All images problematic:** one of `all_alt_text_problems`, `all_empty_alt_text`, `no_alt_text` (error).
- **Some problematic:** one of `mixed_alt_text_problems`, `some_empty_alt_text`, `some_alt_text` (warning).
- **None problematic:** `alt_text_ok` (good).

**extra_data:** `images_without_alt`, `images_with_empty_alt` — arrays of `{ html, url, line, context }` (max 50 examples each).

#### Internal links (`check_internal_links`)

- Count `<a href>` where href starts with `home_url` or `/`.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_internal_links` | improvement | Internal links = 0 **and** word count > 500. |
| `has_internal_links` | good | Internal links > 0. |

#### External links (`check_external_links`)

- Count links with href starting with `http` and not `home_url`. Only adds good.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `has_external_links` | good | At least one external link. |

#### Keyword density (`check_keyword_density`)

- Only runs if focus keyword is set. Density = `(keyword count / word count) * 100` (case-insensitive).

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `keyword_density_low` | warning | Density < 0.5%. |
| `keyword_density_high` | warning | Density > 3%. |
| `keyword_density_ok` | good | 0.5% ≤ density ≤ 3%. |

#### Readability (`check_readability`)

- Sentences split on `[.!?]+`; words and syllables counted (syllable heuristic used).

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `sentence_length` | improvement | Average words per sentence > 20. |
| `sentence_length` | good | Average ≤ 20. |

#### Duplicate content (`check_duplicate_content`)

- Word count from stripped content.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `content_too_short_duplicate` | warning | Word count < 50. |
| `content_sufficient` | good | Word count ≥ 50. |

#### Duplicate titles (`check_duplicate_titles`)

- Current SEO title compared to: (1) other posts’ SEO meta (same meta key), (2) other posts’ `post_title`. Only published posts; exclude current object. Duplicate count and up to 8 edit links in message.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `duplicate_title` | warning | At least one duplicate. |
| `unique_title` | good | No duplicates. |

#### Duplicate meta descriptions (`check_duplicate_meta_descriptions`)

- Same as duplicate titles but for meta description (SEO plugin description key).

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `duplicate_meta_description` | warning | At least one duplicate. |
| `unique_meta_description` | good | No duplicates. |

#### Broken images (`check_broken_images`)

- All `<img src="...">` in content (hidden excluded). URLs normalized (relative → absolute, protocol-relative → site protocol). **Max 25 HTTP checks** (HEAD, fallback GET Range) to avoid timeout.
- **Broken:** HTTP error or unreachable → `broken_images` (error). **External:** URL not under site domain → `external_images` (warning). If no broken and no external → `images_ok` (good).

**extra_data:** `broken_images`: `[{ url, error, line, context }]`. `external_images`: `[{ html, url, line, context }]`. Max 50 examples each.

#### External links validation (`check_external_links_validation`)

- Same external link count as `check_external_links`. If any link contains a “suspicious” domain → `suspicious_links` (warning). Suspicious domains: `bit.ly`, `tinyurl.com`, `goo.gl`, `t.co`, `ow.ly`, `short.link`. Also adds `has_external_links` (good) when external count > 0.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `suspicious_links` | warning | At least one link to a listed shortener domain. |
| `has_external_links` | good | External links > 0. |

#### Contact info (`check_contact_info`)

- **Only for post type `page`.** Regex over content for: email, phone (digits 7–15), address (street/ave/road etc.). Count how many pattern types found.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_contact_info` | warning | Zero contact pattern types. |
| `has_contact_info` | good | At least one type. |

---

### 5.2 Only when full page content is available

#### Broken external links (`check_broken_external_links`)

- Extract all `<a href>`; filter to external (not same origin). **Max 10** links checked via HEAD (no auto redirect). Status: broken (error/timeout) or redirected (301/302/303/307/308).

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `broken_external_links` | error | At least one broken. extra_data: `broken_links`, `total_checked`. |
| `redirected_external_links` | warning | At least one redirect. extra_data: `redirected_links`, `total_checked`. |
| `external_links_ok` | good | Checked > 0 and none broken/redirected. |

#### Broken internal links (`check_broken_internal_links`)

- Same as external but internal links only; **max 15** checked. Relative hrefs converted to absolute using `home_url`.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `broken_internal_links` | error | At least one broken. |
| `redirected_internal_links` | warning | At least one redirect. |
| `internal_links_ok` | good | Checked > 0 and none broken/redirected. |

#### Structured data (`check_structured_data`)

- Count: `application/ld+json` scripts, `itemscope`, and RDFa `vocab="..."`. Total = 0 → issue.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_structured_data` | warning | Total structured data elements = 0. |
| `has_structured_data` | good | Total > 0 (count in message). |

#### Open Graph (`check_open_graph`)

- Required tags: `og:title`, `og:description`, `og:image`, `og:url`, `og:type`. Match: `<meta property="og:...">`.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_open_graph` | warning | None of the required tags found. |
| `incomplete_open_graph` | warning | Some but not all (missing list in message). |
| `complete_open_graph` | good | All five present. |

#### Twitter Cards (`check_twitter_cards`)

- Required: `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`. Match: `<meta name="twitter:...">`.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_twitter_cards` | improvement | None found. |
| `incomplete_twitter_cards` | improvement | Some missing (list in message). |
| `complete_twitter_cards` | good | All four present. |

#### Canonical URL (`check_canonical_url`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_canonical` | improvement | No `<link rel="canonical">`. |
| `has_canonical` | good | Canonical present. |

#### Robots meta (`check_robots_meta`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_robots_meta` | improvement | No `<meta name="robots">`. |
| `has_robots_meta` | good | Present. |

#### Page speed indicators (`check_page_speed_indicators`)

- **Images:** Count `<img>` without `width` or `height` (hidden excluded). If any → `images_without_dimensions` (improvement); extra_data: `images_without_dimensions`: `[{ html, url, line, context }]` (max 50).
- **Inline CSS:** Count `<style>`; if > 3 → `excessive_inline_css` (warning).
- **External resources:** Count `<link href="...">` to external URLs ending in `.css` or `.js`. If external CSS > 5 → `many_external_css` (improvement). If external JS > 5 → `many_external_js` (improvement).
- If no issues in this check → `page_speed_optimized` (good).

#### rel=author (`check_rel_author`)

- Count `<a rel="...author...">`.

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_rel_author` | improvement | Zero. |
| `has_rel_author` | good | At least one (count in message). |

#### Meta viewport (`check_meta_viewport`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_viewport` | improvement | No `<meta name="viewport">`. |
| `viewport_ok` | good | Present. |

#### Favicon (`check_favicon`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_favicon` | improvement | No `<link rel="icon">` or `rel="shortcut icon"`. |
| `favicon_ok` | good | Present. |

#### Language declaration (`check_language_declaration`)

| Key | Severity | Condition |
| ----- | --------- | --------- |
| `no_language` | improvement | No `lang="..."` on `<html>`. |
| `language_ok` | good | Present. |

#### Accessibility basics (`check_accessibility_basics`)

- **Images:** Again count images without `alt` (hidden excluded). If any → `missing_alt_text` (improvement); extra_data optional. If none → `alt_text_ok` (good).
- **Forms:** Count `<input type="text|email|password|search|tel|url">` that have neither `aria-label` nor `id` (for label association). If any → `missing_form_labels` (improvement); else `form_accessibility_ok` (good).

#### Content readability (`check_content_readability`)

- **Content scope:** Prefer `<main>` or `<article>` body; strip header/footer/nav/aside. Then strip tags and normalize spaces.
- **Sentence length:** Sentences split on `[.!?]+`; average words per sentence. ≤15 → `sentence_length_ok` (good); ≤20 → `sentence_length_moderate` (improvement); >20 → `sentence_length_long` (improvement).
- **Paragraph length:** Find `<p>...</p>` (or double line breaks if no `<p>`). Word count per paragraph. If any > 150 words → `long_paragraphs` (improvement); extra_data: `long_paragraphs`: `[{ preview, word_count, line, context }]` (max 50). Else → `paragraph_length_ok` (good). Hidden elements excluded.

---

### 5.3 GSC checks (always run; depend on GSC connection and data)

**Data source:** `get_gsc_keywords_for_url()` — queries `sb2_query_keywords` / `sb2_query_keywords_history` for the page URL (aggregated clicks, impressions, CTR, position). Also `is_used_in_content` per keyword. GSC URL Inspection API for indexing/structured data.

#### GSC status (`check_gsc_status`)

- If GSC not connected, no site URL, or no object URL → add `gsc_status_skipped` (good) and return.
- Call URL Inspection API for the page URL. Use `inspectionResult.indexStatusResult`: `indexingState`, `coverageState`, `verdict`. If `indexingState !== 'INDEXING_ALLOWED'` → add issue with key `gsc_indexing_<state>` (e.g. `gsc_indexing_blocked-by-meta-tag`). Severity from `map_gsc_severity`: BLOCKED_BY_META_TAG / BLOCKED_BY_ROBOTS_TXT → error; else warning. If coverage state present and not PASS, append to message.
- From `inspectionResult.richResultsResult.detectedItems`: for each item’s `items[].issues[]`, add issue `gsc_structured_<richResultType>` (error if severity ERROR, else warning). Message includes rich result type and issue message.
- If no issues from this check → `gsc_status_ok` (good).

#### GSC low CTR good position (`check_gsc_low_ctr_good_position`)

- Keywords where **position < 10** and **CTR < 2%**. If none → `gsc_low_ctr_ok` (good). If any → `gsc_low_ctr_good_position` (low). extra_data: `keywords` (query, clicks, impressions, position, ctr), `aggregated_stats` (totals, avg position, avg ctr, count).

#### GSC high impressions low clicks (`check_gsc_high_impressions_low_clicks`)

- Keywords where **impressions > 1000** and **clicks < 50**. Top 20 by impressions. If none → `gsc_high_impressions_ok` (good). If any → `gsc_high_impressions_low_clicks` (low). extra_data: `keywords`, `aggregated_stats`.

#### GSC keywords not in content (`check_gsc_keywords_not_in_content`)

- Keywords with **is_used_in_content ≤ 0**. Top 20 by traffic (clicks + impressions). If none → `gsc_keywords_in_content` (good). If any → `gsc_keywords_not_in_content` (low). extra_data: `keywords`, `aggregated_stats`.

#### GSC keyword cannibalization (`check_gsc_keyword_cannibalization`)

- Queries where **multiple pages** rank (same query, different page). Current page must be one of the pages. From DB: group by query, page; keep only queries with more than one page. Top 10 keywords by total impressions. extra_data: `cannibalized_keywords`: query → list of `{ page, clicks, impressions, position }`, `current_page`. If none → `gsc_no_cannibalization` (good). If any → `gsc_keyword_cannibalization` (low).

#### GSC longtail opportunities (`check_gsc_longtail_opportunities`)

- Keywords: **word count ≥ 4**, **(clicks > 10 OR impressions > 100)**, **position between 4 and 20** (inclusive). Top 20 by impressions. If any → `gsc_longtail_opportunities` (low). extra_data: `keywords`, `aggregated_stats`. No “good” outcome for this check.

#### GSC content freshness (`check_gsc_content_freshness`)

- Compare last 30 days vs previous 30 days (by date in history). Keywords with **recent_impressions ≥ 100** and **decline > 20%** ( (previous - recent) / previous * 100 ). Top 10 by decline. If none → `gsc_content_freshness_ok` (good). If any → `gsc_content_freshness` (low). extra_data: `keywords` (query, recent_impressions, previous_impressions, recent_clicks, previous_clicks, decline_percentage), `aggregated_stats`.

#### GSC question queries (`check_gsc_question_queries`)

- Query is “question” if it **starts with** a question word (after trim/lowercase). Question words include: what, how, why, when, where, who, which, whose, whom; Spanish (qué, cómo, cuándo, etc.); Danish (hvad, hvordan, …); Swedish (vad, hur, …); German (was, wie, …). Filterable via `seobooster_question_words`. Top 20 by impressions. If any → `gsc_question_queries` (low). extra_data: `keywords`, `aggregated_stats`. No “good” outcome.

---

## 6. Sitewide analysis (separate flow)

**Class:** `SEO_Sitewide_Analysis` in `inc/SEO_Sitewide_Analysis.php`. Runs on **homepage URL only**. Fetches homepage HTML and headers, then runs:

| Check | Key (issue) | Key (good) | Logic |
| ----- | ----------- | ---------- | ----- |
| SSL | `ssl_missing` (error) | `ssl_enabled` | home_url starts with `https://` |
| robots.txt | `robots_txt_missing` (warning), `robots_txt_empty` (improvement) | `robots_txt_exists` | GET home_url/robots.txt; 200 and non-empty body |
| Sitemap | `sitemap_missing` (warning) | `sitemap_exists` | HEAD common sitemap URLs (sitemap.xml, sitemaps.xml, sitemap_index.xml, wp-sitemap.xml); 200 = good |
| Favicon | `favicon_missing` (improvement) | `favicon_exists` | Link rel icon/shortcut icon/apple-touch-icon in HTML or HEAD to /favicon.ico, etc. |
| Viewport | `viewport_missing` (error) | `viewport_exists` | Meta name=viewport in HTML |
| Language | `language_missing` (improvement) | `language_declared` | `<html lang="...">` |

**Score (sitewide):** `100 - (errors*15) - (warnings*8) - (improvements*3) + (good*2)`, then `max(0, min(100, ...))`.

---

## 7. Exclusions

Pages **excluded from analysis** (no save of possibilities, or excluded from lists):

- **Private** posts (`post_status === 'private'`).
- **WooCommerce** special pages: cart, checkout, myaccount, shop (by `wc_get_page_id()`).
- **User exclusion:** post meta `_sb_exclude_from_analysis` = `'1'`.
- **Attachments:** Analysis may run but possibilities are not saved for attachments; they are not listed as having possibilities.

---

## 8. Common replication questions

**How is content obtained for non-WordPress?**  
You need the equivalent of “post content” and “full page HTML” for the URL. For “without full page” you can use stored meta (title, description, focus keyword) plus body text. For “with full page” you must fetch the live URL and run the HTML-based checks on that.

**What if we don’t have GSC?**  
Skip all GSC checks; the plugin adds “skipped” good items when GSC isn’t connected, so the rest of the score still applies. You can omit GSC keys entirely or add a single “GSC not connected” good key.

**Can we change thresholds?**  
Yes. This doc describes the current behavior so you can match it. You can replicate the same keys and severities but with different numbers (e.g. content length 200/2500, or different score weights) if your product requires it.

**Are there rate limits or timeouts?**  
Yes. Broken image checks: max 25 HTTP requests. Broken external links: max 10; broken internal: max 15. Timeouts (e.g. 10s for HEAD) are used to avoid long runs. Replicating systems should apply similar limits.

**What is “hidden element”?**  
SEO Booster skips elements that are considered hidden (e.g. certain classes or attributes) so they don’t affect image/link counts. Exact logic is in `is_hidden_element()` in `SEO_Analysis.php`; you can replicate by defining your own rule (e.g. skip `aria-hidden="true"` or elements inside `noscript`).

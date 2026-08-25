#!/usr/bin/env python3
"""Fill empty msgstr entries in languages/seo-booster-da_DK.po from curated dicts."""

from __future__ import annotations

import re
import sys
from pathlib import Path

PLUGIN_ROOT = Path(__file__).resolve().parent.parent
PO_FILE = PLUGIN_ROOT / "languages" / "seo-booster-da_DK.po"

# Singular translations keyed by exact English msgid (multiline strings use \n).
TRANSLATIONS: dict[str, str] = {
    "Re-run analysis": "Kør analyse igen",
    "Save or publish the page, then re-run analysis (or use the SEO Analysis tab) to see SEO score and possibilities.": (
        "Gem eller udgiv siden, og kør analysen igen (eller brug fanen SEO-analyse) "
        "for at se SEO-score og muligheder."
    ),
    "Inspection returned no clear pass or blocker": "Inspektion gav intet klart OK eller blocker",
    "Google Search Console: Indexing status is inconclusive (URL may be unknown or not yet fully evaluated).": (
        "Google Search Console: Indekseringsstatus er uafklaret "
        "(URL'en er måske ukendt eller endnu ikke fuldt evalueret)."
    ),
    "Google may treat this URL as a soft 404.": "Google behandler måske denne URL som en soft 404.",
    "Google may treat this URL as a soft 404": "Google behandler måske denne URL som en soft 404",
    "Google coverage: %s": "Google-dækning: %s",
    "Canonical mismatch: declared %1$s, Google selected %2$s.": (
        "Kanonisk mismatch: angivet %1$s, Google valgte %2$s."
    ),
    "Page is set to not be indexed (meta robots)": "Siden er sat til noindex (meta robots)",
    "Page is set to not be indexed (HTTP header)": "Siden er sat til noindex (HTTP-header)",
    "Page is disallowed by robots.txt": "Siden er blokeret af robots.txt",
    "Google got a server error when fetching this page": (
        "Google fik en serverfejl ved hentning af siden"
    ),
    "Google was denied access when fetching this page": (
        "Google blev nægtet adgang ved hentning af siden"
    ),
    "Google was forbidden from fetching this page": (
        "Google fik forbud mod at hente siden"
    ),
    "Google hit a redirect error when fetching this page": (
        "Google ramte en redirect-fejl ved hentning af siden"
    ),
    "Google got a 4xx response when fetching this page": (
        "Google fik et 4xx-svar ved hentning af siden"
    ),
    "Google considers this URL invalid": "Google anser denne URL for ugyldig",
    "Google could not fetch this page because of robots.txt": (
        "Google kunne ikke hente siden pga. robots.txt"
    ),
    "Indexing state unknown": "Indekseringsstatus ukendt",
    "%d broken external link(s) found. These links return a 404 and should be fixed or removed.": (
        "%d ødelagte eksterne links returnerer 404 og bør rettes eller fjernes."
    ),
    "%d broken internal link(s) found. These links return a 404 and should be fixed or removed.": (
        "%d ødelagte interne links returnerer 404 og bør rettes eller fjernes."
    ),
    "Focus keyword is not supported by the active SEO plugin.": (
        "Fokusord understøttes ikke af det aktive SEO-plugin."
    ),
    "Indexability could not be determined from the SEO plugin or page robots meta.": (
        "Indekserbarhed kunne ikke fastslås ud fra SEO-plugin eller sidens robots-meta."
    ),
    "Robots meta matches the SEO plugin noindex setting.": (
        "Robots-meta matcher SEO-pluginets noindex-indstilling."
    ),
    "Empty URL": "Tom URL",
    "Invalid or blocked URL": "Ugyldig eller blokeret URL",
    "This URL redirects (HTTP %1$d) to: %2$s. On-page possibilities are not shown for redirected URLs.": (
        "Denne URL redirecter (HTTP %1$d) til: %2$s. "
        "On-page-muligheder vises ikke for redirected URL'er."
    ),
    "This URL redirects (HTTP %d). On-page possibilities are not shown for redirected URLs.": (
        "Denne URL redirecter (HTTP %d). On-page-muligheder vises ikke for redirected URL'er."
    ),
    "This URL redirects. On-page possibilities are not shown for redirected URLs.": (
        "Denne URL redirecter. On-page-muligheder vises ikke for redirected URL'er."
    ),
    "URL not found or unavailable (HTTP %d).": "URL ikke fundet eller utilgængelig (HTTP %d).",
    "URL not found or unavailable (%s).": "URL ikke fundet eller utilgængelig (%s).",
    "URL not found or unavailable.": "URL ikke fundet eller utilgængelig.",
    "SEO Booster: Page overview": "SEO Booster: Sideoverblik",
    "Loading chart…": "Indlæser diagram…",
    "Hover to see the chart": "Hold musen over for at se diagrammet",
    "Ask about rankings, issues, titles, focus keywords, or internal links. Answers use Search Console data and your SEO analysis when available.": (
        "Spørg om placeringer, issues, titler, fokusord eller interne links. "
        "Svar bruger Search Console-data og din SEO-analyse, når det findes."
    ),
    "Suggest several SEO title and meta description ideas for this page.": (
        "Foreslå flere SEO-titel- og metabeskrivelsesidéer til denne side."
    ),
    "Title & meta ideas": "Titel- og meta-idéer",
    "SEO Booster Credits": "SEO Booster Credits",
    "Generate title & meta ideas": "Generer titel- og meta-idéer",
    "Title & meta: 1 credit for 7 titles and 7 meta descriptions. Comprehensive analysis: 3 credits for a full audit (issues, content gaps, internal linking, keyword opportunities, quick wins).": (
        "Titel og meta: 1 credit for 7 titler og 7 metabeskrivelser. "
        "Omfattende analyse: 3 credits for fuld audit (issues, indholdshuller, intern linking, "
        "søgeordsmuligheder, hurtige gevinster)."
    ),
    "Include Markdown version": "Inkluder Markdown-version",
    "Serve an AI-friendly .md version of this page when Markdown discovery is enabled.": (
        "Server en AI-venlig .md-version af siden, når Markdown discovery er aktiveret."
    ),
    "Generating...": "Genererer...",
    "Could not update this possibility. Please try again.": (
        "Kunne ikke opdatere denne mulighed. Prøv igen."
    ),
    "View in Possibilities": "Vis i SEO-muligheder",
    "Possibilities triage requires SEO Booster Pro.": (
        "Prioritering af muligheder kræver SEO Booster Pro."
    ),
    "Could not update status": "Kunne ikke opdatere status",
    "Not found": "Ikke fundet",
    "Redirected": "Redirectet",
    "Fix with Bulk meta": "Ret med Bulk meta",
    "Fix with Image metadata": "Ret med Billedmetadata",
    "Fix with Focus keywords": "Ret med Fokusord",
    "Fix with Autolink opportunities": "Ret med Autolink-muligheder",
    "Open GSC opportunities": "Åbn GSC-muligheder",
    "Open Content decay": "Åbn Content decay",
    "robots.txt file is not accessible (HTTP %d).": "robots.txt er ikke tilgængelig (HTTP %d).",
    "Structured Entity Map is available with SEO Booster Pro. Go beyond llms.txt with machine-readable organization and content relationships.": (
        "Struktureret Entity Map er tilgængelig med SEO Booster Pro. "
        "Gå videre end llms.txt med maskinlæselig organisation og indholdsrelationer."
    ),
    "Import keeps running while you continue. You don\u2019t need to wait here.": (
        "Import kører videre, mens du fortsætter. Du behøver ikke vente her."
    ),
    "This keeps going in the background. You can continue.": (
        "Det fortsætter i baggrunden. Du kan gå videre."
    ),
    "publisher.sameAs is not set (recommended for publisher disambiguation).": (
        "publisher.sameAs er ikke sat (anbefales til tydelig udgiver-identitet)."
    ),
    "Add a chunk or a description/name so a fallback chunk can be generated.": (
        "Tilføj et chunk eller en beskrivelse/navn, så et fallback-chunk kan genereres."
    ),
    "Keep organization name consistent; export rewrites chunk publisher automatically.": (
        "Hold organisationsnavn konsistent; eksport omskriver chunk publisher automatisk."
    ),
    "RELATES_TO accounts for %d%% of all relations (exceeds 20%% threshold).": (
        "RELATES_TO udgør %d%% af alle relationer (over 20%%-grænsen)."
    ),
    "Enable public endpoints in Step 3 and save. Publishing marks the map as self-declared.": (
        "Aktivér offentlige endpoints i trin 3 og gem. Publicering markerer kortet som self-declared."
    ),
    "Step 1 of 3: Set organization details, then build from site data or generate with AI.": (
        "Trin 1 af 3: Angiv organisationsdetaljer, og byg fra sitedata eller generer med AI."
    ),
    "Step 2 of 3: Edit entities, improve with AI, or review suggestions. Still a draft until you publish.": (
        "Trin 2 af 3: Rediger entiteter, forbedr med AI, eller gennemgå forslag. "
        "Stadig kladde, indtil du publicerer."
    ),
    "Step 3 of 3: Enable endpoints and save to publish /entitymap.json and /entitymap.html.": (
        "Trin 3 af 3: Aktivér endpoints og gem for at publicere /entitymap.json og /entitymap.html."
    ),
    "Draft not saved. The live URL is unchanged until you save.": (
        "Kladde ikke gemt. Live-URL'en er uændret, indtil du gemmer."
    ),
    "Live JSON differs from preview. Check for a static entitymap.json in your site root or save your draft.": (
        "Live JSON afviger fra forhåndsvisning. "
        "Tjek for statisk entitymap.json i webrod, eller gem kladden."
    ),
    "Find issues to approve, including optional entity removal.": (
        "Find issues at godkende, inkl. valgfri fjernelse af entiteter."
    ),
    "Please wait. AI is working.": "Vent venligst. AI arbejder.",
    "No preflight issues. Ready to publish.": "Ingen preflight-issues. Klar til publicering.",
    "AI draft ready. Review hubs below, then apply.": (
        "AI-kladde klar. Gennemgå hubs nedenfor, og anvend."
    ),
    "Skipped: focus keyword already set.": "Sprunget over: fokusord er allerede sat.",
    "Skipped: selected fields already have values. Enable overwrite to update.": (
        "Sprunget over: valgte felter har allerede værdier. Aktivér overskrivning for at opdatere."
    ),
    "Markdown cache cleared.": "Markdown-cache ryddet.",
    "Markdown endpoints are disabled.": "Markdown-endpoints er deaktiveret.",
    "Homepage Markdown is not available for this site.": (
        "Markdown for forsiden er ikke tilgængelig for dette site."
    ),
    "This Markdown endpoint has been removed.": "Dette Markdown-endpoint er fjernet.",
    "Markdown page not found.": "Markdown-side ikke fundet.",
    "llms-full.txt is disabled.": "llms-full.txt er deaktiveret.",
    "Full Markdown export generated from curated public WordPress content.": (
        "Fuld Markdown-eksport genereret fra kurateret offentligt WordPress-indhold."
    ),
    "Export limit:": "Eksportgrænse:",
    "Metadata": "Metadata",
    "Published:": "Publiceret:",
    "Modified:": "Ændret:",
    "Latest content": "Seneste indhold",
    "No published posts are currently available.": (
        "Ingen publicerede indlæg er tilgængelige lige nu."
    ),
    "Full export": "Fuld eksport",
    "Full site markdown": "Markdown for hele sitet",
    "Suggest unique focus keywords from imported GSC data for pages missing one: no AI required. Picks the highest-impression query per URL and skips utility pages.": (
        "Foreslå unikke fokusord fra importerede GSC-data for sider uden fokusord: ingen AI. "
        "Vælger forespørgslen med flest eksponeringer pr. URL og springer hjælpesider over."
    ),
    "Publish a structured Entity Map so AI systems understand your organization, key content, and relationships, beyond llms.txt.": (
        "Publicer et struktureret Entity Map, så AI-systemer forstår din organisation, "
        "nøgleindhold og relationer, ud over llms.txt."
    ),
    "Batch complete in %1$s: %2$d processed, %3$d failed.": (
        "Batch færdig på %1$s: %2$d behandlet, %3$d fejlede."
    ),
    "Cancelled after %1$s: %2$d processed, %3$d failed.": (
        "Annulleret efter %1$s: %2$d behandlet, %3$d fejlede."
    ),
    "-": "-",
    "Skipped: fields already filled (no AI call).": (
        "Sprunget over: felter er allerede udfyldt (intet AI-kald)."
    ),
    "Custom keyword: edit or pick a suggestion below.": (
        "Brugerdefineret søgeord: rediger eller vælg et forslag nedenfor."
    ),
    "Step 1: Sources & organization": "Trin 1: Kilder og organisation",
    "llms.txt is not enabled yet. Sync still uses whatever curation settings are saved there.": (
        "llms.txt er ikke aktiveret endnu. Sync bruger stadig de gemte kurationsindstillinger der."
    ),
    "Creates or updates your draft only. Nothing is public until you publish in step 3.": (
        "Opretter eller opdaterer kun kladden. Intet er offentligt, før du publicerer i trin 3."
    ),
    "Step 2: Edit & refine": "Trin 2: Rediger og finpuds",
    "No crawl gaps found, or bot tracking has no mapped content hits yet.": (
        "Ingen crawl-gaps fundet, eller bot tracking har endnu ingen mapped content hits."
    ),
    "Step 3: Publish live": "Trin 3: Publicer live",
    "entitymap.html title": "entitymap.html-titel",
    "Optional custom title tag for entitymap.html. Leave blank for the default: EntityMap · site name.": (
        "Valgfri custom title tag til entitymap.html. Lad stå tom for standard: EntityMap · sitenavn."
    ),
    "Uses imported Google Search Console data only: no AI required. For each page without a focus keyword, suggests the highest-impression GSC query for that URL (minimum 20 impressions, position 1 to 50), skipping utility pages and keywords already used on another page.": (
        "Bruger kun importerede Google Search Console-data: ingen AI. "
        "For hver side uden fokusord foreslås GSC-forespørgslen med flest eksponeringer "
        "(min. 20, position 1 til 50), springer hjælpesider og søgeord brugt på anden side over."
    ),
    "Writing focus keywords to %s: used for on-page analysis, keyword term checks, and internal link suggestions.": (
        "Skriver fokusord til %s: bruges til sideanalyse, søgeordstjek og interne linkforslag."
    ),
    "Ready to scan: GSC data only, no AI setup needed.": (
        "Klar til scan: kun GSC-data, ingen AI-opsætning nødvendig."
    ),
    "Select items in the table, then process selected, or process all matching results.": (
        "Vælg elementer i tabellen, og behandl valgte eller alle matchende resultater."
    ),
    "Build a curated llms.txt file for AI crawlers and answer engines. SEO Booster generates the content for you. It does not upload a physical file to your server unless you download and place one yourself.": (
        "Byg en kurateret llms.txt til AI-crawlere og svarmotorer. SEO Booster genererer indholdet. "
        "Den uploader ikke en fysisk fil til serveren, medmindre du selv downloader og lægger en."
    ),
    "Pro: Publish a structured Entity Map (/entitymap.json + /entitymap.html) so AI systems understand your organization and content relationships, beyond the llms.txt index.": (
        "Pro: Publicer et struktureret Entity Map (/entitymap.json + /entitymap.html), "
        "så AI-systemer forstår organisation og indholdsrelationer ud over llms.txt-indekset."
    ),
    "No physical llms.txt file was found in your WordPress root. When enabled, SEO Booster serves /llms.txt dynamically via WordPress (recommended: stays in sync when you publish content).": (
        "Ingen fysisk llms.txt i WordPress-roden. Når aktiveret serverer SEO Booster /llms.txt "
        "dynamisk via WordPress (anbefalet: holdes synkroniseret ved publicering)."
    ),
    "HTML": "HTML",
    ".md": ".md",
    "Markdown discovery (Pro)": "Markdown discovery (Pro)",
    "Serve AI-friendly Markdown versions of your pages (*.md and /index.md) and an optional full-site export at /llms-full.txt. Markdown responses are noindex and not meant for Google Search ranking.": (
        "Server AI-venlige Markdown-versioner (*.md og /index.md) og valgfri fuld-site-eksport på "
        "/llms-full.txt. Markdown-svar er noindex og ikke til Google Search-placering."
    ),
    "Pretty permalinks are recommended. Plain permalinks may prevent /page.md and /llms-full.txt from working.": (
        "Pæne permalinks anbefales. Plain permalinks kan forhindre /page.md og /llms-full.txt i at virke."
    ),
    "Endpoints": "Endpoints",
    "Serve Markdown URLs (*.md and /index.md)": "Server Markdown-URL'er (*.md og /index.md)",
    "Serve /llms-full.txt and link it from llms.txt": (
        "Server /llms-full.txt og link fra llms.txt"
    ),
    "Markdown discovery signals": "Markdown discovery-signaler",
    "Add alternate text/markdown link in HTML head (allowed pages)": (
        "Tilføj alternate text/markdown-link i HTML head (tilladte sider)"
    ),
    "Send HTTP Link header for Markdown (allowed pages)": (
        "Send HTTP Link-header for Markdown (tilladte sider)"
    ),
    "llms-full limit": "llms-full-grænse",
    "Maximum curated pages in /llms-full.txt (1–250). Uses the same curation as llms.txt.": (
        "Maks. kuraterede sider i /llms-full.txt (1 til 250). Bruger samme kuration som llms.txt."
    ),
    "Markdown cache TTL": "Markdown cache TTL",
    "Seconds to cache generated .md bodies (60–86400). Cleared when you save content or clear cache.": (
        "Sekunder til cache af genererede .md-kroppe (60 til 86400). "
        "Ryddes ved gem af indhold eller manuel cache-rydning."
    ),
    "llms-full cache TTL": "llms-full cache TTL",
    "Clear Markdown cache": "Ryd Markdown-cache",
    "View /index.md": "Vis /index.md",
    "Sample .md": "Eksempel .md",
    "View /llms-full.txt": "Vis /llms-full.txt",
    "Writing to %s: generated titles and descriptions will be saved to this plugin\u2019s SEO fields.": (
        "Skriver til %s: genererede titler og beskrivelser gemmes i dette SEO-plugins felter."
    ),
    "Build from GSC clicks and AI bot traffic, then edit names, relations, and source chunks": (
        "Byg fra GSC-klik og AI bot-trafik, og rediger navne, relationer og source chunks"
    ),
    "Publish /entitymap.json and /entitymap.html so AI systems understand your organization, key content, and relationships, curated from GSC and bot traffic, with optional AI drafts (Pro).": (
        "Publicer /entitymap.json og /entitymap.html, så AI-systemer forstår organisation, "
        "nøgleindhold og relationer, kurateret fra GSC og bot-trafik, med valgfri AI-kladder (Pro)."
    ),
    "Upgrade to Pro": "Opgrader til Pro",
    "Each row still lists every open possibility on that URL.": (
        "Hver række viser stadig alle åbne muligheder på den URL."
    ),
    "Checks that apply to the whole site: SSL, robots, sitemap, favicon, language, and more.": (
        "Tjek der gælder hele webstedet: SSL, robots, sitemap, favicon, sprog og mere."
    ),
    "Log entries": "Logposter",
    "Internal plugin events for troubleshooting. Search or refresh to reload the latest entries.": (
        "Interne plugin-hændelser til fejlfinding. Søg eller opdater for at indlæse seneste poster."
    ),
    "This content was moved to trash. On-page possibilities are not shown until it is published again.": (
        "Indholdet er flyttet til papirkurv. On-page-muligheder vises ikke, før det publiceres igen."
    ),
    "This content was deleted. On-page possibilities are not shown until the URL is published again.": (
        "Indholdet er slettet. On-page-muligheder vises ikke, før URL'en publiceres igen."
    ),
    "This archive was deleted. On-page possibilities are not shown until the URL exists again.": (
        "Arkivet er slettet. On-page-muligheder vises ikke, før URL'en findes igen."
    ),
    "Scroll to reload": "Scroll for at genindlæse",
    "404 monitoring is turned off": "404-overvågning er slået fra",
    "Existing activity stays available below. Turn monitoring on in Settings to collect new 404 and redirect hits.": (
        "Eksisterende aktivitet er stadig tilgængelig nedenfor. "
        "Slå overvågning til i Indstillinger for at indsamle nye 404- og redirect-hits."
    ),
    "Activity": "Aktivitet",
    "404 errors and redirects recorded on your site. Search, sort, and remove rows you no longer need.": (
        "404-fejl og redirects registreret på dit site. Søg, sortér og fjern rækker du ikke længere har brug for."
    ),
    "SEO Booster skips a range of common URLs (robots.txt, favicon, .well-known, and similar) so noise does not fill this report.": (
        "SEO Booster springer mange almindelige URL'er over (robots.txt, favicon, .well-known osv.), "
        "så støj ikke fylder rapporten."
    ),
    "Show all %d ignored patterns": "Vis alle %d ignorerede mønstre",
    "Data management": "Datahåndtering",
    "Clear the activity log if you want to start fresh. This cannot be undone.": (
        "Ryd aktivitetsloggen, hvis du vil starte forfra. Det kan ikke fortrydes."
    ),
    "Automatic linking is turned off": "Automatiske links er slået fra",
    "Your keyword rules are saved, but links are not applied on the front end until you turn automatic linking back on.": (
        "Dine søgeordsregler er gemt, men links anvendes ikke på frontenden, "
        "før du slår automatiske links til igen."
    ),
    "Open Automatic Links settings": "Åbn indstillinger for automatiske links",
    "Enter a keyword and the URL it should link to. Works with internal and external links.": (
        "Angiv et søgeord og URL'en det skal linke til. Virker med interne og eksterne links."
    ),
    "Code & preformatted blocks": "Kode og præformaterede blokke",
    "Form fields & controls": "Formularfelter og kontroller",
    "SVG graphics": "SVG-grafik",
    "How links are placed": "Sådan placeres links",
    "The system scans your content and adds links where the keyword appears. These areas are skipped, matching Settings → Automatic Links → Where not to link.": (
        "Systemet scanner indhold og tilføjer links, hvor søgeordet optræder. "
        "Disse områder springes over, jf. Indstillinger → Automatiske links → Hvor der ikke linkes."
    ),
    "Currently excluded": "Aktuelt udelukket",
    "No optional HTML areas are excluded right now. Linking can appear in headings, lists, and blockquotes.": (
        "Ingen valgfrie HTML-områder er udelukket lige nu. "
        "Links kan vises i overskrifter, lister og blockquotes."
    ),
    "Change excluded elements in Settings": "Ændr udelukkede elementer i Indstillinger",
    "Link rules": "Linkregler",
    "Add keywords or phrases that should be changed into links. Double-click a keyword or URL cell to edit it.": (
        "Tilføj søgeord eller sætninger, der skal blive til links. "
        "Dobbeltklik en søgeord- eller URL-celle for at redigere."
    ),
    "Beta": "Beta",
    "Your data privacy is our priority. All information is processed locally on your server and sent to your email address from your own server. We never access your data.": (
        "Dit datas privatliv er vores prioritet. Al information behandles lokalt på din server "
        "og sendes til din e-mail fra din egen server. Vi får aldrig adgang til dine data."
    ),
    "Keywords and pages": "Søgeord og sider",
    "Search and filter keywords imported from Google Search Console.": (
        "Søg og filtrer søgeord importeret fra Google Search Console."
    ),
    "Optional Pro feature: track broken links visitors hit on your site.": (
        "Valgfri Pro-funktion: spor ødelagte links besøgende rammer på dit site."
    ),
    "Blocks bots when detected: more reliable than robots.txt because it applies at request time on your server.": (
        "Blokerer bots ved detektion: mere pålideligt end robots.txt, "
        "fordi det gælder ved request-tid på din server."
    ),
    "Warning: This will permanently change your database. Proceed with care. There's no going back!": (
        "Advarsel: Dette ændrer databasen permanent. Vær forsigtig. Det kan ikke fortrydes!"
    ),
    "On WordPress 7 with Connectors, SEO Booster can suggest titles, meta descriptions, and more, right where you edit.": (
        "På WordPress 7 med Connectors kan SEO Booster foreslå titler, metabeskrivelser og mere, "
        "direkte hvor du redigerer."
    ),
    "You\u2019re on a local site. Google sign-in may need a public URL.": (
        "Du er på et lokalt site. Google-login kan kræve en offentlig URL."
    ),
    "SEO Booster can scan for these simple issues. Open a tool when you\u2019re ready. Nothing runs from here.": (
        "SEO Booster kan scanne efter disse simple issues. Åbn et værktøj, når du er klar. "
        "Intet kører herfra."
    ),
    "Open any post. The SEO Booster box shows analysis, Search Console keywords, and AI tools.": (
        "Åbn et indlæg. SEO Booster-boksen viser analyse, Search Console-søgeord og AI-værktøjer."
    ),
    "Review on-page improvements found by the scan: titles, meta, headings, images, and more.": (
        "Gennemgå on-page-forbedringer fra scanningen: titler, meta, overskrifter, billeder og mere."
    ),
}

# Plural translations: msgid -> (msgstr[0], msgstr[1]).
PLURAL_TRANSLATIONS: dict[str, tuple[str, str]] = {
    "%d entity has no source chunk (EntityMap v1.0 requires at least one).": (
        "%d entitet har intet source chunk (EntityMap v1.0 kræver mindst ét).",
        "%d entiteter har ingen source chunks (EntityMap v1.0 kræver mindst ét).",
    ),
    "%d chunk publisher does not match publisher.name (will be corrected on export).": (
        "%d chunk publisher matcher ikke publisher.name (korrigeres ved eksport).",
        "%d chunk publishers matcher ikke publisher.name (korrigeres ved eksport).",
    ),
    "%d Concept has no sameAs (recommend external grounding).": (
        "%d Concept har intet sameAs (anbefales ekstern grounding).",
        "%d Concepts har intet sameAs (anbefales ekstern grounding).",
    ),
    "%d URL": (
        "%d URL",
        "%d URL'er",
    ),
}


def parse_po_string(raw: str) -> str:
    """Decode a quoted PO string fragment (UTF-8 literals plus \\n, \\t, etc.)."""
    if not raw:
        return ""
    if raw.startswith('"') and raw.endswith('"'):
        raw = raw[1:-1]
    result: list[str] = []
    i = 0
    while i < len(raw):
        if raw[i] == "\\" and i + 1 < len(raw):
            esc = raw[i + 1]
            if esc == "n":
                result.append("\n")
                i += 2
            elif esc == "t":
                result.append("\t")
                i += 2
            elif esc == '"':
                result.append('"')
                i += 2
            elif esc == "\\":
                result.append("\\")
                i += 2
            else:
                result.append(raw[i])
                i += 1
        else:
            result.append(raw[i])
            i += 1
    return "".join(result)


def escape_po_string(value: str) -> str:
    """Encode a string as a single quoted PO literal."""
    escaped = (
        value.replace("\\", "\\\\")
        .replace('"', '\\"')
        .replace("\n", "\\n")
        .replace("\t", "\\t")
    )
    return f'"{escaped}"'


def wrap_po_continuations(text: str, width: int = 76) -> list[str]:
    """Split text into PO continuation lines similar to gettext wrapping."""
    if not text:
        return ['""\n']

    lines: list[str] = []
    remaining = text
    while remaining:
        if len(remaining) <= width:
            lines.append(escape_po_string(remaining) + "\n")
            break
        split_at = remaining.rfind(" ", 0, width + 1)
        if split_at <= 0:
            split_at = width
        chunk = remaining[:split_at]
        if split_at < len(remaining) and remaining[split_at] == " ":
            chunk += " "
        lines.append(escape_po_string(chunk) + "\n")
        remaining = remaining[split_at:].lstrip()
    return lines


def format_po_field(key: str, value: str) -> list[str]:
    """Format msgstr or msgstr[n] field lines."""
    if not value:
        return [f"{key} \"\"\n"]
    if "\n" not in value and len(value) < 72:
        return [f"{key} {escape_po_string(value)}\n"]
    parts = value.split("\n")
    out = [f"{key} \"\"\n"]
    for idx, part in enumerate(parts):
        segment = part
        if idx < len(parts) - 1:
            segment += "\n"
        out.extend(wrap_po_continuations(segment))
    return out


def read_po_field(lines: list[str], start: int) -> tuple[str, int]:
    """Read msgid / msgid_plural / msgstr field starting at start index."""
    line = lines[start].rstrip("\n")
    match = re.match(r"^(msgid(?:_plural)?|msgstr(?:\[\d+\])?)\s+(.*)", line)
    if not match:
        return "", start + 1
    value = parse_po_string(match.group(2).strip())
    i = start + 1
    while i < len(lines) and lines[i].startswith('"'):
        value += parse_po_string(lines[i].strip())
        i += 1
    return value, i


def process_po_file(path: Path) -> tuple[int, list[str]]:
    """Fill empty translations in place."""
    with path.open(encoding="utf-8") as fh:
        lines = fh.readlines()

    filled = 0
    still_missing: list[str] = []
    out: list[str] = []
    i = 0

    while i < len(lines):
        if not lines[i].startswith("msgid "):
            out.append(lines[i])
            i += 1
            continue

        block_start = i
        msgid, i = read_po_field(lines, i)
        is_plural = False
        if i < len(lines) and lines[i].startswith("msgid_plural "):
            is_plural = True
            _, i = read_po_field(lines, i)

        msgstr_start = i
        if is_plural:
            msgstr_values: list[str] = []
            while i < len(lines) and lines[i].startswith("msgstr["):
                value, i = read_po_field(lines, i)
                msgstr_values.append(value)
            is_empty = len(msgstr_values) < 2 or not msgstr_values[0] or not msgstr_values[1]
        else:
            msgstr_values = []
            if i < len(lines) and lines[i].startswith("msgstr "):
                value, i = read_po_field(lines, i)
                msgstr_values = [value]
            is_empty = not msgstr_values or not msgstr_values[0]

        block_end = i
        out.extend(lines[block_start:msgstr_start])

        if msgid and is_empty:
            if is_plural and msgid in PLURAL_TRANSLATIONS:
                singular, plural = PLURAL_TRANSLATIONS[msgid]
                out.extend(format_po_field("msgstr[0]", singular))
                out.extend(format_po_field("msgstr[1]", plural))
                filled += 1
            elif not is_plural and msgid in TRANSLATIONS:
                out.extend(format_po_field("msgstr", TRANSLATIONS[msgid]))
                filled += 1
            else:
                out.extend(lines[msgstr_start:block_end])
                still_missing.append(msgid)
        else:
            out.extend(lines[msgstr_start:block_end])

    with path.open("w", encoding="utf-8", newline="\n") as fh:
        fh.writelines(out)

    return filled, still_missing


def main() -> int:
    if not PO_FILE.is_file():
        print(f"Error: PO file not found: {PO_FILE}", file=sys.stderr)
        return 1

    filled, still_missing = process_po_file(PO_FILE)

    print(f"Filled {filled} empty translation(s) in {PO_FILE}")
    if still_missing:
        print(f"Warning: {len(still_missing)} empty msgid(s) had no translation:", file=sys.stderr)
        for msgid in still_missing:
            preview = msgid.replace("\n", "\\n")
            if len(preview) > 100:
                preview = preview[:100] + "..."
            print(f"  - {preview}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

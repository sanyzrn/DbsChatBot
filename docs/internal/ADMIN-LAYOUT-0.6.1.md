# Admin layout 0.6.1-beta

Removed fixed outer width caps from every admin page and the setup wizard. WordPress keeps its sidebar and leading gutter; the plugin provides a matching 20px desktop / 10px mobile trailing gutter in both host directions. Text introductions and the device preview retain readable widths.

Grids shrink within their containers, form fields fill available columns, repeaters shrink safely, module actions wrap, and dashboard readiness uses responsive columns. All 12 admin tables have focusable, labelled scroll regions; data tables retain a readable minimum width while key/value tables can wrap.

Validation on local WordPress 7.1 / PHP 8.5 / SQLite:
- All 14 view templates pass PHP syntax checks.
- Browser DOM geometry checked ten main routes at 390, 768, 1280 and 2560px.
- FAQ, analytics, all five wizard steps, case details and pharma setup also checked; narrow/wide checks at 320 and 1920px include both Persian and English administration.
- Dashboard at 1920px uses 1720px of content with the expanded 160px WordPress sidebar and 20px side gutters.
- No plugin elements overflow the viewport outside their own table scroll regions. English WordPress itself produces a 1px toolbar overflow on the short FAQ screen at 320px.
- Browser screenshot capture was unavailable; checks used live rendered DOM dimensions, not a screenshot review.

Install the 0.6.1-beta ZIP to apply this change to another site. Versioned assets invalidate the previous plugin CSS URL.

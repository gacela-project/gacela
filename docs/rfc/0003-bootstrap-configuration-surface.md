# RFC-0003: The bootstrap configuration surface

- Status: **accepted** (2026-08-12). The audit table below is enforced by a test, so this stopped being a proposal the moment it merged.
- Relates to: #684, #525, #549, #478. Sequencing: #676 first among the open additions — it was the only one that shrinks the surface, and it shipped.

## Why this exists

`GacelaConfig` has **38 public methods** today (excluding `__construct`; counted from `src/Framework/Bootstrap/GacelaConfig.php`, and the gate below re-counts on every run). Six open issues — #672, #673, #674, #675, #676, #679 — each add at least one more. Without a written rule, six independent authors will each invent one.

The rule already exists. It was settled by #525 (which named 33 methods as the defect) and #549 (which answered with one primary path per intent), and it lived as one paragraph inside [getting-a-dependency](../getting-a-dependency.md) — a page about reading dependencies, where nobody adding a bootstrap method would look. This RFC is that paragraph, promoted to doctrine and given teeth.

## The policy

> Reading a config value has **six** methods for one intent and nobody has ever complained, because they are the same path with typed variants, discovered together, impossible to confuse. The problem in the other intents was never the count. It was that unrelated mechanisms competed for the same job with no indication which one you were supposed to use.

The count was never the problem. Two ways to do one job, with no rule for choosing, always was. After #549 named a primary path per intent, the surface grew from 33 to 38 with nobody violating anything — which is the proof that the count was the wrong metric.

## One: the competition test

A new `GacelaConfig` method is **fine** when:

- it serves an intent nothing else serves, or
- it is a typed or conditional variant of a path that already exists, discovered next to it (`addBindingIf()` next to `addBinding()`, `enableFileCache()` over `setFileCache(true)`).

A new method is a **defect** when a reader with a job in hand cannot tell which of two methods does it. That question is answerable at review time, unlike a count.

Applied to the six open issues at the time of writing: #675 and #673 pass (new intents, no competitor). #676 improves the surface (four `addSuffixType*()` verbs become sugar over one `addResolvableType()`). #672 fails twice as proposed (two verbs for one intent, and a third way to group services after `tag()` and `addHandlerRegistry()` — the #478 rule). #674 fails as proposed (a second extend path with no rule for choosing). #679 fails as proposed (a second answer to *which class wins*, next to `setProjectNamespaces()`). "Fails as proposed" means the issue must name the rule for choosing before it adds the method, not that the feature is unwanted.

**What "fails as proposed" turned into.** Three of those have since been answered rather than refused, which is the outcome this test is for:

- **#676** shipped as scored, and the four suffix verbs are now sugar over `addResolvableType()`.
- **#672** shipped with *one* verb instead of two — `addPluginStack()` appends on repeat, the way `tag()` does — and with a rule separating the three collections: a registry answers *the one implementation for this key*, a tag answers *all of these* untyped, a stack answers *all implementations of this interface*, typed. The contract is the separator.
- **#674**'s rule turned out to be the one `tag()` and `Container::tag()` already use: `extendService()` wraps an id *wherever it is registered*, `extendProviderService()` wraps it *only as the named Provider registers it*. Everywhere versus there.
- **#679**'s objection weakened on its own: #686 fixed the cache poisoning that was half its motivation, leaving `setProjectNamespaces()` answering *which tree wins across modules* and a resolution context answering *which variant inside one module wins*. Different questions, so not competitors.

## Two: the naming grammar

Five prefixes, each with one meaning. A reader who learns five prefixes navigates any number of methods:

| Prefix | Meaning |
|---|---|
| `add*` | contributes to a collection; calling twice contributes twice |
| `declare*` | states a shape or contract, checked by something else later |
| `set*` | replaces a value; calling twice keeps the last |
| `enable*` | toggles behavior on (sugar over the `set*` it shadows) |
| `extend*` | wraps or composes something already registered |

The grammar governs **new** methods. Existing methods that predate it are recorded below as exceptions, not renamed — churning every `gacela.php` in existence to satisfy a prefix would invert the cost the grammar exists to avoid.

## Three: the audit

Every public method, its bucket, and its verdict. **This table is a gate**: `tests/Integration/Architecture/BootstrapSurfaceDocsTest.php` fails when a public `GacelaConfig` method has no row here, or a row names a method that no longer exists. Adding a method means adding a row, which means writing down which rule admits it.

| Method | Bucket | Verdict |
|---|---|---|
| `addAlias()` | `add*` | conforms |
| `addAppConfig()` | `add*` | conforms |
| `addAppConfigKeyValue()` | `add*` | conforms — typed variant of `addAppConfig()` |
| `addAppConfigKeyValues()` | `add*` | conforms — plural variant, same path |
| `addBinding()` | `add*` | conforms |
| `addBindingIf()` | `add*` | conforms — conditional variant of `addBinding()` |
| `addConfigDimension()` | `add*` | conforms — a new intent nothing else serves: `APP_ENV` selects configuration and nothing else could, so a second selector had no way to exist. Ordered, and calling twice contributes twice, which is what makes `add*` the right prefix over `set*` |
| `addExternalService()` | `add*` | conforms |
| `addFactory()` | `add*` | conforms |
| `addHandlerRegistry()` | `add*` | conforms |
| `addHealthCheck()` | `add*` | conforms |
| `addLazy()` | `add*` | conforms |
| `addPlugin()` | `add*` | conforms |
| `addPlugins()` | `add*` | conforms — plural variant, same path |
| `addPluginStack()` | `add*` | conforms — declares an extension point and contributes to one with the same verb, appending like `tag()`, so there is no second verb to choose between. Distinct from `tag()` and `addHandlerRegistry()` by carrying an interface contract, which is the rule the docs state |
| `addProtected()` | `add*` | conforms |
| `addResolvableType()` | `add*` | conforms — a new intent nothing else serves (declaring a class kind, not a suffix for an existing one), and the four `addSuffixType*()` verbs became sugar over it |
| `addSuffixTypeConfig()` | `add*` | conforms; #676 folds the four into `addResolvableType()` |
| `addSuffixTypeFacade()` | `add*` | conforms; see above |
| `addSuffixTypeFactory()` | `add*` | conforms; see above |
| `addSuffixTypeProvider()` | `add*` | conforms; see above |
| `afterResolving()` | — | exception: the verb names the container lifecycle moment it hooks; `addAfterResolvingCallback()` would name the mechanics and lose the moment |
| `declareConfigSchema()` | `declare*` | conforms |
| `declareDtoSchema()` | `declare*` | conforms |
| `disableEventListeners()` | — | exception: `enable*`'s negative twin; the grammar deliberately has no `disable*` row because most toggles default off, and this one defaults on |
| `enableFileCache()` | `enable*` | conforms — sugar over `setFileCache(true)` |
| `extendGacelaConfig()` | `extend*` | exception in meaning: composes another configuration surface into this one rather than wrapping a registered service; predates the grammar |
| `extendGacelaConfigs()` | `extend*` | same as `extendGacelaConfig()`, plural variant |
| `extendProviderService()` | `extend*` | conforms — the choosing rule against `extendService()` is the one `tag()` and `Container::tag()` already use: everywhere versus there |
| `extendService()` | `extend*` | conforms |
| `getExternalService()` | — | exception: the one read path on a write surface, paired with `addExternalService()` for the bridge handshake |
| `loadDefinitions()` | — | exception: imports wiring from a file or array; `add*` would hide that the argument is data, not a definition |
| `registerGenericListener()` | — | exception: semantically `add*`; predates the grammar, recorded rather than renamed |
| `registerSpecificListener()` | — | same as `registerGenericListener()` |
| `resetInMemoryCache()` | — | exception: an operational instruction to the bootstrap, not surface configuration; both host bridges call it per boot (#666) |
| `setAppModulePaths()` | `set*` | conforms |
| `setFileCache()` | `set*` | conforms |
| `setEventDispatcher()` | `set*` | conforms — replaces the dispatcher rather than adding to what listens, and takes precedence over `disableEventListeners()`, which governs the one Gacela would build |
| `setProjectNamespaces()` | `set*` | conforms |
| `setStubsDir()` | `set*` | conforms |
| `tag()` | — | exception: the domain word is the API; `addTag()` would read as tagging a tag |
| `toTransfer()` | — | exception: internal egress to the setup DTO; `@internal` in spirit, listed because it is public |
| `validateConfigSchemaOnBoot()` | — | exception: semantically `enable*` (`enableConfigSchemaValidationOnBoot()` conforms and says less); recorded rather than renamed |
| `when()` | — | exception: the head of the contextual-binding DSL (`when(X)->needs(Y)->give(Z)`); a prefix would break the sentence it starts |

Eleven exceptions, each with the reason it stays. A twelfth needs a reason this table can hold.

## Non-goals

- **Splitting `GacelaConfig` into facets** (`$config->schema()->declare(...)`): breaks the flat surface the docs teach, churns every `gacela.php` in existence, and the settled doctrine says the count was never the complaint.
- **Renaming existing methods**: the grammar governs new methods; the audit records the old ones.
- **A hard cap on the method count**: the count is not the metric.

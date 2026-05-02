<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# Linguistic Resource Refactoring Plan

## Purpose

This note turns the current architectural direction into an operational refactoring plan.

It is intentionally more concrete than `IA.md` and is meant to guide implementation slices.

## Working Assumptions

### No legacy migration

This project has no installed legacy base to preserve.

Practical consequence:

- no data migration needs to be designed,
- no compatibility layer is needed for old schemas,
- the simplest valid strategy is to drop and recreate the database when the storage model changes.

This should remain the default assumption unless the project later acquires real external installations.

### Terminology

Use the following rule consistently:

- `Translation` and `Glossary` remain user-facing product terms,
- `Linguistic Resource` is the generic developer term,
- `translation` and `glossary` are concrete resource kinds.

### Architectural stance

The main differentiator should not be a thin facade alone.

The preferred implementation model is:

- abstract base classes for shared linguistic-resource behavior,
- concrete derived classes for translation-specific and glossary-specific behavior,
- PHP and JavaScript following the same conceptual split.

A composition root or admin facade may still exist, but it should not carry the core specialization logic.

## Refactoring Goal

Replace the current translation-only structural model with a shared linguistic-resource model in which:

- common behavior lives in abstract PHP and JS classes,
- translation and glossary behavior live in derived classes,
- storage is resource-centric rather than translation-centric,
- the first glossary implementation reuses the same editing and persistence concepts as translations.

## Target Conceptual Model

### Generic domain object

A linguistic resource is a source-target aligned collection of entries plus metadata describing how it is authored, edited, persisted, matched, and compiled.

Shared concepts:

- resource identity,
- source locale,
- target locale,
- entries,
- target forms,
- plural-aware mapping,
- status/provenance or variant metadata,
- optional links to other resources,
- optional compilation state for provider synchronization.

### Specializations

#### Translation

A translation resource is characterized by:

- source entries extracted from a plugin catalog,
- exact identity of source strings,
- one expected target value per relevant plural form,
- integration with WordPress translation build/runtime workflows.

#### Glossary

A glossary resource is characterized by:

- user-authored or curated source terms,
- possible exact and partial matching modes,
- one preferred target plus optional alternates,
- use in QA and provider glossary synchronization rather than direct WordPress gettext lookup.

## Recommended PHP Design

### Abstract base classes

The core shared behavior should live in abstract classes rather than in one large service class.

Recommended first layer:

1. `AbstractLinguisticResource`
   - identity and common metadata,
   - resource-kind contract,
   - entry access,
   - serialization hooks,
   - validation hooks.

2. `AbstractLinguisticResourceEntry`
   - source-side identity,
   - source text and optional plural source text,
   - target access by form,
   - ordering/context metadata.

3. `AbstractLinguisticResourceTarget`
   - target text,
   - form index,
   - shared metadata container.

4. `AbstractLinguisticResourceRepository`
   - load/save one resource,
   - load/save entries and targets,
   - delete/reset operations,
   - shared transaction boundaries.

5. `AbstractLinguisticResourceEditorModel`
   - edit-screen view model assembly,
   - payload normalization,
   - validation and persistence orchestration.

### Concrete PHP classes

Recommended first concrete classes:

1. `TranslationResource extends AbstractLinguisticResource`
2. `GlossaryResource extends AbstractLinguisticResource`
3. `TranslationResourceEntry extends AbstractLinguisticResourceEntry`
4. `GlossaryResourceEntry extends AbstractLinguisticResourceEntry`
5. `TranslationResourceRepository extends AbstractLinguisticResourceRepository`
6. `GlossaryResourceRepository extends AbstractLinguisticResourceRepository`
7. `TranslationEditorModel extends AbstractLinguisticResourceEditorModel`
8. `GlossaryEditorModel extends AbstractLinguisticResourceEditorModel`

### Shared-vs-derived rule

Put behavior in the abstract layer only if both resource kinds need it with the same invariants.

Examples of shared responsibilities:

- plural-aware target addressing,
- common validation flow,
- canonical payload normalization,
- shared persistence orchestration skeleton.

Examples of derived responsibilities:

- translation source extraction and exact source identity rules,
- glossary partial-match metadata and preferred-variant rules,
- provider glossary compilation behavior,
- translation build/export integration.

### Relationship with current admin classes

`AdminPage` can remain a composition root, but it should stop being the place where resource behavior is decided.

The target split is:

- admin wiring in controller/facade classes,
- resource behavior in abstract and derived domain/editor classes,
- storage behavior in resource repositories,
- rendering in UI classes.

## Recommended JavaScript Design

### Why JS needs the same split

The current translation editor script is translation-specific and procedural.

If glossaries are added without a shared editor model, the repository will likely end up with:

- duplicated form serialization,
- duplicated status/provenance handling,
- duplicated filtering/search/edit logic,
- diverging UI behavior between translations and glossaries.

### Abstract JS layer

Introduce an abstract editor class mirroring the PHP domain split.

Recommended first layer:

1. `AbstractLinguisticResourceEditor`
   - bootstrapping from localized config,
   - row discovery,
   - payload serialization,
   - common filtering/search,
   - form change tracking,
   - save payload preparation,
   - common event wiring skeleton.

2. `AbstractLinguisticResourceRow`
   - row element access,
   - source/target field access,
   - status/meta access,
   - serialization hooks.

### Concrete JS classes

Recommended first concrete classes:

1. `TranslationEditor extends AbstractLinguisticResourceEditor`
2. `GlossaryEditor extends AbstractLinguisticResourceEditor`
3. `TranslationRow extends AbstractLinguisticResourceRow`
4. `GlossaryRow extends AbstractLinguisticResourceRow`

### JS implementation note

Because the current file is plain browser JavaScript without a build step, there are two acceptable implementation styles:

1. ES2015 classes, if the project accepts that syntax in admin assets.
2. Constructor functions plus prototypes, if strict continuity with the current script style is preferred.

The architectural requirement matters more than the syntax choice:

- one shared base editor abstraction,
- one derived translation editor,
- one derived glossary editor later.

## Target Storage Direction

### Principle

The database should become resource-centric instead of translation-centric.

Recommended conceptual tables:

1. `i18nly_linguistic_resources`
2. `i18nly_linguistic_resource_entries`
3. `i18nly_linguistic_resource_targets`
4. `i18nly_linguistic_resource_links`
5. `i18nly_linguistic_resource_compilations`

### Practical rule for this repository

Do not spend time designing migrations from the current translation tables.

Instead:

- create the new schema directly,
- delete and recreate local data as needed,
- keep the implementation simple,
- optimize for clarity of the new model rather than backward compatibility.

### Current-post anchor question

A WordPress post anchor can still be kept for translation resources because the admin workflow already depends on it.

Recommended practical rule:

- keep a WordPress post anchor where it simplifies permissions, menu integration, and edit-screen navigation,
- move business identity and business content to the new resource-centric tables.

Glossary resources may later use either:

- the same post-anchor strategy,
- or dedicated admin pages without an equivalent post anchor,

but that decision does not need to block the common model.

## Operational Refactoring Strategy

### Guiding rule

Do not attempt a big-bang rewrite.

Use small slices that progressively introduce the abstraction while keeping one clear next step at all times.

### Slice 0: Freeze naming and scope

Goal:

- agree on the generic names before code moves.

Deliverables:

- this note,
- `IA.md` as high-level architecture reference,
- chosen class naming conventions.

### Slice 1: Introduce PHP abstract classes without changing product behavior

Goal:

- create the shared PHP abstraction layer while keeping translations as the only implemented resource kind.

Deliverables:

- `AbstractLinguisticResource`,
- `TranslationResource`,
- first abstract editor/repository contracts,
- translation code adapted to instantiate the translation-derived classes.

Validation:

- existing translation tests stay green,
- no glossary behavior added yet.

### Slice 2: Introduce resource-centric schema and repositories

Goal:

- replace translation-centric business tables with the new resource-centric tables.

Repository-specific simplification:

- reset the database instead of writing migrations.

Deliverables:

- new schema manager,
- new repositories,
- translation persistence routed through the new tables,
- deletion of obsolete schema code when the new path is complete.

Validation:

- focused repository and persistence tests,
- manual translation creation/edit/save check if needed.

### Slice 3: Introduce JS editor abstraction for translations

Goal:

- make the current translation editor the first concrete implementation of a generic resource editor.

Deliverables:

- abstract editor base,
- translation editor derived class,
- translation row abstraction,
- preserved current edit behavior.

Validation:

- existing JS/PHP integration behavior preserved,
- no glossary UI yet.

### Slice 4: Add first glossary resource backend

Goal:

- prove the abstraction with a second resource kind on the backend first.

Deliverables:

- `GlossaryResource`,
- `GlossaryResourceRepository`,
- minimal CRUD model,
- first glossary-specific validation rules.

Validation:

- focused repository and model tests,
- no need for complete UX yet.

### Slice 5: Add first glossary editor UI

Goal:

- prove the JS abstraction with a second concrete editor.

Deliverables:

- `GlossaryEditor`,
- glossary row implementation,
- first dedicated glossary admin screen or translation-side glossary block,
- basic save/load round-trip.

Validation:

- glossary CRUD works,
- translation UX remains stable.

### Slice 6: Connect glossary resources to translations

Goal:

- support attachment or reuse of glossaries from translation workflows.

Deliverables:

- resource links,
- ordering rules,
- first compilation model for provider sync,
- first QA usage of glossary resources in translation editing.

Validation:

- linked glossary resolution is deterministic,
- translation editor can consume glossary-derived guidance.

## Recommended Deletions

To keep the refactoring honest, actively delete obsolete structures instead of keeping parallel dead models for long.

Candidates for deletion once replaced:

- translation-only storage code that duplicates resource-centric persistence,
- translation-only editor state assembly that becomes a special case of the generic editor model,
- any schema code retained only for hypothetical legacy migration.

## Non-Goals

This refactoring should not try to solve everything at once.

Explicit non-goals for the first phase:

- no full revision/history design,
- no provider-agnostic AI platform,
- no complete glossary sync UX from day one,
- no attempt to preserve a nonexistent legacy database.

## Key Design Decision Summary

1. Keep `Translation` and `Glossary` as product terms.
2. Use `Linguistic Resource` as the generic developer abstraction.
3. Implement the distinction mainly through abstract and derived classes in PHP and JS.
4. Prefer composition roots for wiring, not for core specialization logic.
5. Replace the current schema directly instead of designing migrations.
6. Validate the abstraction by making translations the first concrete resource and glossaries the second.

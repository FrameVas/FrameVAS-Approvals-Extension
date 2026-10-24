# FrameVAS – Approvals Lite for EspoCRM

![EspoCRM 9.0+](https://img.shields.io/badge/EspoCRM-9.0%2B-2f6bb3)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![Languages](https://img.shields.io/badge/languages-English%20%7C%20Ukrainian-4c9a2a)

**English** | [Українська](README.uk.md)

Approvals Lite adds a new field type, **Approves**, to EspoCRM. Add it to any entity – documents, transfers, contracts, requests – choose who must approve each record, and let them approve or decline in one click. The field tracks every decision, notifies the right people at the right time, and shows an overall approval status you can use in lists, filters, Kanban, workflows, formulas and PDF templates.

## Features

- **Approves field type** – add it to any entity in the Entity Manager, like any other field.
- **Two approval modes** – *Parallel* (everyone approves in any order) or *Stages* (stage 2 starts only after everyone in stage 1 has approved).
- **Approve / Decline buttons** – shown only to the user whose turn it is, with an optional comment.
- **Overall status field** – `Pending`, `Approved` or `Declined`, created automatically; usable in filters, Kanban, reports, workflows and BPM.
- **Notifications** – approvers are notified when it's their turn; the record owner is notified about each decision.
- **Audit log in the Stream** – readable entries for each decision and each change to the approver list.
- **Search filter** – find records by approver, by decision, or "waiting for me".
- **List view display** – a counter (`1/3 Pending`), colored approver names, or both.
- **PDF templates** – readable text, a ready-made table, a configurable table helper and loop data.
- **Formula support** – read approvers, stages and decisions in formula scripts.
- **Protection** – users can't fake decisions; approvals are recorded only through the approve/decline action, by the approver themselves.
- **Translations** – English and Ukrainian included; easy to add more.

## Requirements

- EspoCRM 9.0 or newer (tested on EspoCRM 10)
- PHP 8.3 or newer

## Installation

1. Download the latest extension package (`.zip`) from the [Releases](../../releases) page.
2. In EspoCRM, go to **Administration → Extensions**.
3. Click **Upload**, choose the downloaded file and click **Install**.
4. Go to **Administration → Clear Cache**.
5. Reload the page in your browser.

### Upgrading from the pre-release "Approvals" package

If your system has the earlier package named **Approvals**, simply install this package over it. The installer takes over the old extension's registry entry automatically and keeps all your data.

> ⚠️ **Do not click "Uninstall" on the old "Approvals" extension.** Both packages share the same files, so uninstalling the old one would delete them.

## Quick start

1. **Add the field.** Go to **Administration → Entity Manager → *your entity* → Fields → Add Field**, choose the type **Approves**, enter a label and save. EspoCRM adds the `c` prefix to custom field names, so a field named `approves` becomes `cApproves`. A second field, **Approves Status** (`cApprovesStatus`), is created automatically.
2. **Add it to layouts.** In **Layouts**, add the field to *Detail* (and optionally *List*, *Side Panel Fields* and *Search Filters*). Add **Approves Status** too if you want to see or filter by the overall result.
3. **Grant access.** Approvers only need **read** access to the entity in their roles. To print PDFs, users also need read access to **PDF Templates**.
4. **Use it.** Open a record in edit mode, click **Add Approver**, choose users, and save. The approvers are notified and can approve or decline from the record.

## Field options

| Option | Description |
|---|---|
| **Required** | At least one approver must be added. |
| **Approval Mode** | **Parallel** – all approvers can act at the same time, in any order. **Stages** – approvers are grouped into stages; a stage starts when everyone in the previous stage has approved. |
| **Hide Comment on Approve** | Approving only asks for confirmation, without a comment box. |
| **Hide Comment on Decline** | Declining only asks for confirmation, without a comment box. |
| **Approve/Decline Buttons Position** | **Below the approvers** (default), **Above the approvers**, or **Record header** (next to the Edit button). |
| **List View Display** | **Summary** (`1/3 Pending`), **Approvers** (colored names), or **Summary + Approvers**. |
| **Audited** | Logs decisions and approver list changes in the record's Stream. |
| **Read-only** | Standard EspoCRM option. Decisions are still possible. |
| **Read-only After Create** | The approver list can be set only when the record is created; it is locked afterwards. Decisions are still possible, and formulas, workflows and BPM can still change the list. |

## How approvals work

- **Parallel mode** – every approver can approve or decline at any time.
- **Stages mode** – only approvers of the current stage can act. Later stages show *Waiting* and are notified when their stage starts. In edit mode, use **Add Stage** and the ↑ ↓ arrows to arrange approvers.
- **A single Decline** at any point makes the overall status *Declined* and stops the process.
- **When everyone has approved**, the overall status becomes *Approved*.
- **Who can act** – only the approver whose turn it is, and only for themselves. Approvers need just read access to the record.
- **Editing the list** – users who can edit the record can add, remove and reorder approvers. Existing decisions are always kept. To reset someone's decision, remove them, save, then add them again.
- **Statuses on the detail view** – each approver has a colored label (green *Approved*, orange *Pending*, red *Declined*, grey *Waiting*), with the decision date and comment. In Stages mode, each stage is marked *Done*, *Current* or *Waiting*.

## Notifications

- When an approver's turn starts, they receive **"Your approval is needed: …"**.
- When someone approves or declines, the record's **assigned user** (or its creator) is notified.
- Notifications are written in each recipient's own language.

## Audit log (Stream)

With the **Audited** option enabled (and the entity's Stream enabled), the Stream shows:

- **Decisions** – "Bob approved this document", with the stage and comment.
- **Approver list changes** – added, removed and moved approvers (e.g. *Moved: Carol: Stage 1 → Stage 2*).

For the overall result, you can also tick EspoCRM's standard **Audited** option on the **Approves Status** field to log *Pending → Approved*.

## Search filter

Add the Approves field to **Layouts → Search Filters**. In the list view, open the filter menu, choose the field and a condition:

| Condition | Shows records where… |
|---|---|
| **Includes Any Of** | at least one chosen user is an approver |
| **Includes All Of** | all chosen users are approvers |
| **Doesn't Include** | none of the chosen users are approvers |
| **Approved By** / **Declined By** | the chosen user made that decision |
| **Waiting For** | it's the chosen user's turn now |

Use the **Me** button for the current user. **Waiting For → Me** + **Save Filter** gives each user their own "waiting for my approval" list.

> The quick search box searches text fields only; use the filter to search by approvers.

## Kanban

Set **Approves Status** as the entity's **Status Field** in the Entity Manager to group records into *Pending*, *Approved* and *Declined* columns. Column names are translated automatically; you can rename them in the **Label Manager**.

## Formula

In formula scripts, the field value is a list of approvers. Each approver is an object with:

| Key | Value |
|---|---|
| `id` | user ID |
| `name` | user name |
| `stage` | stage number (always `1` in Parallel mode) |
| `status` | `Pending`, `Approved` or `Declined` |
| `resolvedAt` | decision date-time, empty while pending |
| `comment` | decision comment, if any |

The overall result is in the `cApprovesStatus` attribute.

**Example – react to the final result:**

```
if (cApprovesStatus == 'Approved' && entity\isAttributeChanged('cApprovesStatus')) {
    description = string\concatenate(description, '\nApproved by everyone');
}
```

**Example – check whether all approvers of stage 1 have approved:**

```
$items = cApproves;
$i = 0;
$total = 0;
$approved = 0;

while ($i < array\length($items)) {
    $item = array\at($items, $i);

    if (object\get($item, 'stage') == 1) {
        $total = $total + 1;

        if (object\get($item, 'status') == 'Approved') {
            $approved = $approved + 1;
        }
    }

    $i = $i + 1;
}

if ($total > 0 && $approved == $total) {
    // Stage 1 is complete.
}
```

**Example – add members of a team as stage 1 approvers on new records:**

```
if (entity\isNew()) {
    $teamId = record\findOne('Team', null, null, 'name=', 'Director');

    if ($teamId) {
        $userIds = record\findRelatedMany('Team', $teamId, 'users', 100, 'userName', 'asc', 'isActive=', true);
        $list = cApproves;

        if (!$list) {
            $list = list();
        }

        $i = 0;

        while ($i < array\length($userIds)) {
            $item = object\create();
            object\set($item, 'id', array\at($userIds, $i));
            object\set($item, 'name', record\attribute('User', array\at($userIds, $i), 'name'));
            object\set($item, 'stage', 1);
            object\set($item, 'status', 'Pending');
            $list = array\push($list, $item);
            $i = $i + 1;
        }

        cApproves = $list;
    }
}
```

Users who are already in the list are not duplicated by the extension, and new approvers always start as *Pending*.

## PDF templates

The extension adds approver data to **PDF Templates** automatically. Texts are printed in the language of the user who prints, dates in their time zone.

| Placeholder | Result |
|---|---|
| `{{cApproves}}` | Text: *Bob (Approved), Carol (Pending)* |
| `{{cApprovesStatus}}` | The overall status |
| `{{{cApprovesTable}}}` | A ready-made table (note the **three** braces) |
| `{{approvesTable cApprovesList columns="…"}}` | A table with the columns you choose |
| `{{#each cApprovesList}}…{{/each}}` | A loop over approvers |

### Configurable table

```
{{approvesTable cApprovesList columns="stage,approver,title,status,date"}}
{{approvesTable cApprovesList columns="number,approver:Full name,title,signature"}}
{{approvesTable cApprovesList columns="approver,emailAddress,phoneNumber,status"}}
```

| Column | Shows |
|---|---|
| `number` | row number |
| `stage` | stage number (hidden in Parallel mode) |
| `approver` | user name |
| `title` | user's Title field |
| `status` | decision (translated) |
| `date` | decision date |
| `comment` | decision comment |
| `signature` | an empty cell for a signature |
| *any User field* | e.g. `emailAddress`, `phoneNumber`, `gender`, `teams`, `defaultTeam`, or custom fields like `cDepartment` |

- Rename a header with `key:Header`, e.g. `approver:Full name`.
- Columns where every value is empty are hidden automatically; add `hideEmpty="false"` to always show them.
- Unknown column names are skipped, so a typo doesn't break the PDF.

### Loop values

Inside `{{#each cApprovesList}}`: `name`, `title`, `status`, `statusRaw`, `stage`, `stageLabel`, `resolvedAt`, `comment`, and the flags `isApproved`, `isDeclined`, `isPending`, `isWaiting`.

> Tip: place loops outside of HTML tables, or use the configurable table helper instead – the template editor moves `{{#each}}` tags that are placed between table rows.

### Example template

```html
<h2>{{name}}</h2>
<p><b>Approval status:</b> {{cApprovesStatus}}</p>
<p><b>Approvers:</b> {{cApproves}}</p>

<h3>Approval sheet</h3>
<div>{{approvesTable cApprovesList columns="stage,approver,title,status,date,comment"}}</div>

<h3>Signatures</h3>
<div>{{approvesTable cApprovesList columns="number,approver,title,signature"}}</div>
```

## API

Decisions are recorded through a dedicated endpoint:

```
POST /api/v1/Approves/{entityType}/{id}/{field}/resolve

{"action": "approve", "comment": "Optional text"}
```

`action` is `approve` or `decline`. The request is accepted only from the approver whose turn it is. The response contains the updated field value and the overall status.

## Translations

English and Ukrainian are included. To add a language, copy `custom/Espo/Modules/Approvals/Resources/i18n/en_US` to a folder with the EspoCRM language code (e.g. `de_DE`), translate the values (keep keys and placeholders like `{user}` unchanged) and clear the cache.

# سجل قرارات ونواقص PlayNexus

**تاريخ المراجعة:** 2026-09-15  
**الحالة:** مراجعة موثقة؛ ليست موافقة جديدة على نطاق أو إعلان جاهزية للإطلاق  
**النطاق:** PRD، الوثائق 00–22، `DESIGN.md`، OpenAPI، `.ai/`، المسارات والكود والاختبارات الحالية.

هذا الملف يجمع نتيجة المطابقة في مكان واحد. يظل [PRD](01-PRD-Baseline.md) أصل نية المنتج، و[BRD](02-BRD.md)/[SRS](03-SRS.md) عقد المتطلبات، و[سجل القرارات](../.ai/DECISIONS.md) مرجع ما وافق عليه المالك. وجود بند هنا لا يحوله تلقائيًا إلى ميزة MVP معتمدة. `LOCALLY_ENGINEERING_ACCEPTED` للمراحل المنفذة لا يعني أن كل متطلبات PRD تحققت أو أن المنتج `PILOT_READY`.

## 1. صورة الـSaaS الفعلية

| القدرة | الواقع الحالي | الدليل / القرار |
| --- | --- | --- |
| شركة عميل مستقلة | **منفذ محليًا:** `tenants`، إنشاء بواسطة Super Admin مع مالك أول مدعو، تفعيل/تعليق، ملف شركة. | [PRD-MVP-001](01-PRD-Baseline.md#4-exact-mvp-capability-inventory)، [التأسيس](../database/migrations/2026_09_10_000003_create_tenant_branch_foundation.php)، [الإنشاء](../app/Http/Controllers/PlatformTenantController.php) |
| فروع وموظفو كل شركة | **منفذ محليًا:** `branches` و`users.tenant_id` و`branch_user` وعزل النطاق؛ المالك يدير الشركة والموظفين، ومدير الفرع يدير Reception/Cashier داخل فروعه فقط. | [PRD-MVP-002/003](01-PRD-Baseline.md#4-exact-mvp-capability-inventory)، [العلاقات](../app/Models/User.php)، [الإدارة](../app/Http/Controllers/StaffStatusController.php) |
| Commercial plans and subscriptions | **Code implemented and SQLite-green under the approved 2026-09-15 decision:** `plans`, historical `subscriptions` with one current pointer, editable EGP prices, snapshots, enforced quantitative limits, trial/grace/restricted access, expiring overrides, Super Admin/Tenant Owner UIs, audit, and manual `subscription_billing_records`. **Acceptance remains PARTIAL** until MySQL concurrency and real-browser flows are executed. Recurring billing and production commercial validation remain deferred/external. | `.ai/DECISIONS.md` 2026-09-15; `database/migrations/2026_09_15_000025_create_subscription_catalog.php`; focused OQ-04 tests in `.ai/TEST_RESULTS.md` |

The PRD defines PlayNexus as SaaS. The 2026-09-15 decision approves bounded editable plans, limits, trial/grace and manual SaaS billing. The code slice is implemented and SQLite-green; MySQL concurrency and real-browser acceptance are still unverified. Recurring billing and production commercial validation remain deferred; this ledger does not claim commercial production readiness.

## 2. قرارات المنتج المعتمدة وحدودها

الجدول يلخص القرارات المؤثرة على الفجوات، ولا يحل محل النص الأصلي في [`.ai/DECISIONS.md`](../.ai/DECISIONS.md) أو سجل [BRD §OQ](02-BRD.md#14-open-questions-and-decision-log).

| القرار | المعتمد الآن | حد التنفيذ / الاعتماد الباقي |
| --- | --- | --- |
| OQ-01/02/03/06/23 | SaaS بعلامة واحدة، Egypt/EGP/Africa-Cairo والعربية/الإنجليزية؛ مدفوعات حضورية فقط؛ قارئ QR كإدخال لوحة مفاتيح؛ تشغيل online-only. | لا بوابة دفع أو offline writes أو white-label أو SDK جهاز خاص. اعتماد الضريبة والنشر للمنشأة منفصل. |
| OQ-07/15/17 | أقل بيانات لازمة، موافقة ولي أمر موثقة، DOB اختياري، هاتف ولي الأمر فريد داخل الشركة ويعاد استخدام الأسرة القائمة. | تنفيذ الاحتفاظ/الإخفاء والاعتماد Legal/DPO باقٍ في GAP-08. |
| OQ-08/09/18 | إيصال مرقم وغير قابل لإعادة استخدام رقمه؛ استرداد نقدي كامل واحد في نفس الفرع واليوم المحلي بموافقة منفصلة؛ تذكرة مستخدمة غير قابلة للاسترداد. | لا استرداد جزئي أو provider call؛ اعتماد Finance/Legal للإطلاق باقٍ. |
| OQ-10/11/12/16/19 | أدوار متعددة النطاق، سعة check-in صارمة، تحقق ولي الأمر أو override مدقق، تسعير immutable، تسليم Reception→Cashier ثم تسوية نقدية ذرية. | صلاحيات Branch Manager للموظفين وsupport access غير مكتملة؛ اختبارات/موافقة التشغيل لا تُستبدل بالقرار. |
| OQ-14 | دخول دعم Super Admin لبيانات الشركة ممنوع افتراضيًا، ولا يسمح به إلا least-privilege مؤقت وبسبب وتدقيق. | العقد معتمد لكن workflow غير منفذ: GAP-07. |
| قرار M4 Egypt | لا pause/resume في MVP الحالي؛ التمديد 30 دقيقة؛ adjustment إضافي ومسبب؛ cancellation ليست refund. | PRD/SRS/اختبارات قديمة ما زالت تسمي pause Must: DOC-01. إعادة إضافتها تحتاج قرار نطاق جديد. |
| OQ-20/24 | إدارة الحوادث ووردية/درج الكاشير مؤجلتان. | لا تُحسبان عيب تنفيذ في النطاق الحالي؛ توحيد حالة الوثائق مطلوب. |

## 3. حالة القرارات والمدخلات المفتوحة

| القرار | الحالة القانونية للقرار | ما بقي فعليًا |
| --- | --- | --- |
| OQ-04 — Commercial Owner | **RESOLVED_APPROVED 2026-09-15:** Starter/Growth/Professional/Enterprise, limits, trial/grace lifecycle and manual billing. | Implementation acceptance remains partial; recurring provider billing is separately deferred. |
| OQ-05/OQ-13 — Product baseline | **RESOLVED_APPROVED 2026-09-15:** WhatsApp first, SMS fallback, email receipt/admin, 10-minute lead, 2/5-minute retry and three-attempt cap. | Provider, sender identity, bilingual templates, callback capability and cost ownership remain separate procurement/operations decisions; local transport is not customer delivery. |
| OQ-21 — Product targets | **RESOLVED_APPROVED 2026-09-15:** p95/availability and bounded-query targets are fixed. | Environment-specific normal/peak workload counts and deployed measurement are Engineering/Architecture/QA release evidence. |
| OQ-22 — Security baseline | **RESOLVED_APPROVED 2026-09-15:** idle/password/revocation/MFA/audit/secrets/debug baseline is fixed. | Absolute timeout, exact rate limits and audit/log retention are separate registered decisions; deployed validation is a release gate. |

**قرارات ليست مفتوحة:** OQ-09 وOQ-19 حُسمتا ونُفذ المسار النقدي المحدود؛ OQ-20 وOQ-24 حُسمتا بالتأجيل. أي موضع ما زال يسمي OQ-24 «مفتوحًا» أو يمنع M4 هو حالة وثيقة قديمة، لا سلطة قرار جديدة.

## 4. نواقص تنفيذ مؤكدة في النطاق الحالي

`CONFIRMED` هنا يعني مطابقة المتطلب بالكود الحالي، لا اختبار Browser جديد لهذا الملف. الأولوية ترتب أثر المستخدم/الأمان؛ لا تمنح صلاحية تلقائية لتنفيذ نطاق مختلف.

| ID / أولوية | الناقص فعليًا | الدليل وقيد الإغلاق |
| --- | --- | --- |
| GAP-01 / CLOSED 2026-09-15 | **أُغلق:** تعرض شاشة POS علاقات ولي الأمر/الطفل المؤهلة فقط بهاتف مقنّع وتاريخ خدمة أصلي، وترسل الحقائق نفسها للتسعير والمسودة والخصم. يتحقق الخادم من العلاقة عند التسعير والإنشاء ويعيد التحقق قبل الدفع/إصدار التذكرة؛ ولا تُحمّل خيارات الأسرة لمستخدم الاستقبال غير المخول بالبيع. | [PRD-MVP-008](01-PRD-Baseline.md#4-exact-mvp-capability-inventory)، [واجهة الطلب](../resources/views/pos/index.blade.php)، [تحقق السيرفر](../app/Actions/ProcessOrdinaryPosOrder.php). الدليل: 27 اختبارًا/230 تحققًا ورحلة Browser معزولة quote→draft→cash→receipt؛ التفاصيل في [نتائج الاختبار](../.ai/TEST_RESULTS.md). |
| GAP-02 / CLOSED 2026-09-15 | **أُغلق:** تبديل EN↔AR يرسل `locale` قبل تعطيل واجهة الطلب ويحفظ الوجهة على login والصفحات المحمية. | [JavaScript](../resources/js/app.js)، [الاختبار](../tests/Feature/LocaleSwitchTest.php)، ورحلة Browser فعلية. |
| GAP-03 / CLOSED 2026-09-15 | **أُغلق:** Branch Manager يدير حالة وتعيين Reception/Cashier في فروعه النشطة فقط؛ المالك/المدير/الفروع الأخرى مخفية أو ممنوعة مع منع escalation. | [السياسات](../app/Policies/BranchPolicy.php)، [الإدارة](../app/Http/Controllers/StaffStatusController.php)، واختبارات staff/assignment. |
| GAP-04 / CLOSED 2026-09-15 | **أُغلق:** الإيراد occurrence-based من posted payments وexecuted refunds، مع breakdown بالمنتج/نوع التذكرة/طريقة الدفع وتوزيع deterministic يطابق الصافي بلا double counting. | [التقرير](../app/Http/Controllers/ReportController.php)، [الاختبار](../tests/Feature/M6ReportsTest.php). |
| GAP-05 / CLOSED 2026-09-15 | **أُغلق:** العرض والتصدير يحملان عملة وتوقيت الفرع، Net ظاهر، والسياق المختلط معلّم صراحة؛ USD/America-New_York وRTL/LTR مثبتة بالمتصفح. | [واجهة التقرير](../resources/views/reports/index.blade.php)، [الترجمة](../lang/en/reports.php). |
| GAP-06 / CLOSED 2026-09-15 | **أُغلق:** login/logout/reset/session revocation والمنع الحساس تسجل audit آمنًا مع correlation وبصمة route غير كاشفة، والمستخدم المجهول يسجل identity hash فقط. | [دعم التدقيق](../app/Support/AuthenticationAudit.php)، [الوسيط](../app/Http/Middleware/AuditSensitiveDenial.php)، واختبارات المصادقة. |
| GAP-07 / P1 | **OQ-14 support access/break-glass غير منفذ:** المنصة تنشئ/تعلق الشركات، لكنها لا تملك جلسة دعم مؤقتة/مراجعة/سبب/تدقيق tenant-visible. عدم وجود blanket Super Admin bypass صحيح أمنيًا وليس بديلًا للworkflow. | [قرار OQ-14](../.ai/DECISIONS.md)، [المصفوفة §11](08-Permission-Matrix.md)، [مسارات المنصة](../routes/platform.php). الإغلاق: عقد أقل صلاحية مع اختبارات انتهاء/إلغاء/تدقيق، بعد قرار Security/Legal التنفيذي. |
| GAP-08 / P1 release | **تنفيذ retention/anonymization/legal-hold غير موجود:** السياسة المعتمدة ثلاث سنوات بعد آخر زيارة/إغلاق مع استثناءات موثقة؛ لا job/command/request workflow يطبقها. | [سياسة PRD/SRS](03-SRS.md)، [القرار](../.ai/DECISIONS.md)، [متطلب الإنتاج SEC-PII-003](03-SRS.md). الإغلاق: إجراء موثق ومجرب واعتماد Legal/DPO؛ لا حذف آلي قبل ذلك. |
| GAP-09 / CLOSED 2026-09-15 | **أُغلق:** سجل الجلسة يعرض المدة والتمديد والتعديل والقيمة النهائية والدفع/الاسترداد/الإيصال والتحقق وسبب override من حقائق immutable؛ pause خارج النطاق. | [استعلام/عرض الجلسات](../app/Http/Controllers/ReportController.php)، [الواجهة](../resources/views/reports/index.blade.php)، واختبار Browser عربي. |

## 5. قدرات مخططة أو مؤجلة — لا تعرضها كمنفذة

| القدرة | الحالة الحالية | ما يلزم قبل التنفيذ/القبول |
| --- | --- | --- |
| SaaS plans/subscriptions and plan limits | Models, migration, lifecycle command, enforcement, Super Admin/Tenant Owner UIs, audit and manual billing are implemented and SQLite-green. Acceptance remains partial pending MySQL concurrency and real-browser execution; recurring billing remains deferred. | Approved OQ-04 decision 2026-09-15; `.ai/TEST_RESULTS.md`; no provider is inferred. |
| `/api/v1` الخارجي | OpenAPI عقد مخطط؛ `bootstrap/app.php` يربط web فقط ولا توجد `/api/v1` routes. `/app` session-auth هو الموجود. | تحديد المستهلك/auth/versioning ثم implementation واختبارات API؛ لا تعد validators دليل نشر. |
| إشعارات حقيقية وcallbacks/manual resend | `notification_messages`/attempts وjob محلي يثبت `sent` لمحاكاة database؛ ليس إرسال SMS/Email/WhatsApp ولا `delivered`. | OQ-05/OQ-13 والموافقات/المفاتيح وإثبات callback. |
| حوادث الأطفال | workflow مشروط ومؤجل OQ-20؛ safety notes المقيدة موجودة. | قرار Product/Safety/Legal جديد، ثم schema/routes/permissions/UI/tests. |
| Pause/resume | مصدر PRD يذكرهما، لكن قرار Egypt M4 يستبعدهما؛ لا routes/pause table. | قرار تغيير نطاق صريح فقط، وليس تنفيذًا مستنتجًا من SRS قديمة. |
| وردية ودرج الكاشير | مؤجل OQ-24؛ المدفوعات تحمل branch/cashier/time للتسوية اللاحقة. | قرار Finance/Operations جديد. |
| Parent portal، ألعاب/طوابير، عضويات/ولاء، online gateway، offline writes، white-label | خارج MVP المعتمد. | تغير نطاق موثق قبل أي implementation. |

## 6. تناقضات وثائق وأدلة تشغيل يجب تسويتها

| ID | التناقض / الخطر | المصدران اللذان يجب توحيدهما |
| --- | --- | --- |
| DOC-01–09 / CLOSED 2026-09-15 | **سُوِّيت التناقضات الحالية:** no-pause Egypt، حالات M4/M6، خطة UX، actual schema مقابل target، `/app` مقابل `/api/v1`، stack الفعلي، rollback خطوتين، دليل الاختبارات الحالي، وحدود WF-08/09. التاريخ الأقدم باقٍ كسجل وموسوم أو متجاوز بملحق أعلى الوثيقة، لا كحالة حالية. | [Milestones](15-Delivery-Milestones.md)، [ERD](07-Database-ERD.md)، [API](09-API-Specification.md)، [Tooling](12-Tooling-and-Delivery-Guide.md)، [Runbook](22-M6-Operations-Runbook.md)، [Wireframes](10-UI-UX-Wireframes.md) |

## 7. بوابات الإطلاق الخارجية

حتى بعد سد النواقص المحلية، **`PILOT_READY` غير مثبت** دون: staging محدد واختبارات نشر عليه، مراقبة وتنبيهات deployed، backup/restore من بيئة النشر، اعتماد Finance/Legal/DPO للضريبة/الإيصال/الخصوصية والاحتفاظ، تدريب وUAT وتوقيع موظفي الفرع، ومسؤول go/no-go مسمى. Hosted CI الأول لا يزال بوابة خارجية مستقلة. انظر [`.ai/BLOCKERS.md`](../.ai/BLOCKERS.md) و[Runbook](22-M6-Operations-Runbook.md).

## 8. ترتيب معالجة مقترح — ليس موافقة تنفيذ

1. **مغلق محليًا:** GAP-01–06 وGAP-09 وDOC-01–09.
2. **محجوب بقرار خارجي:** GAP-07 يحتاج عقد Security/Legal تنفيذي؛ GAP-08 يحتاج اعتماد Legal/DPO قبل أي حذف أو anonymization.
3. **بوابات وقرارات متبقية:** كود OQ-04 ناجح على SQLite لكن يلزم تحقق MySQL ومتصفح؛ مدخلات provider/sender/template/callback/cost والـabsolute timeout/rates/retention/RPO-RTO مسجلة كأسئلة دقيقة؛ أحجام OQ-21 وقياسات النشر دليل إطلاق وليست سؤال Product جديدًا. لا يُخترع أي منها في الكود.

**طريقة إغلاق أي بند:** تعديل المتطلب/القرار المعتمد إذا تغيّر النطاق، تنفيذ أصغر vertical slice، اختبار مثبت مناسب للمخاطر (SQLite لا يُحسب MySQL)، تحديث ملفات `docs/` و`.ai/` ذات الصلة، ثم ربط نتيجة الاختبار الفعلية في [`.ai/TEST_RESULTS.md`](../.ai/TEST_RESULTS.md). لا تُحوِّل هذا السجل أو validator الوثائق إلى دليل قبول منتج أو إنتاج.

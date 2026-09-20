# خطة المعالجة الشاملة لتجربة PlayNexus

## 2026-09-19 actor dashboard screenshot review

Reviewed all 30 existing Platform, Owner, Manager, Reception, and Cashier captures across English/Arabic and 390/820/1440px. The main mobile issue was that full-height KPI blocks delayed the role's primary action; the Reception 820px three-metric grid also left an empty tinted cell. The Platform menu hid destinations behind horizontal scrolling, and mixed English branch names reordered in Arabic text.

The implemented dashboard keeps alerts first, moves staff actions ahead of metrics, uses compact two-column phone metrics without a blank tablet cell, wraps Platform navigation, groups Platform status counts, and isolates data names with bidirectional markup. Existing routes, permissions, financial calculations, and tenant/branch scopes are unchanged. Current browser checks cover Owner/Reception/Cashier phone views and Platform English desktop/Arabic phone views; the original 30-image set remains a baseline, with reviewed new captures in `deliverables/qa/actor-dashboards-enhanced/`.

الحالة: **تم تنفيذ وإغلاق المعالجة المحلية عبر M6:** shell/sidebar، Desktop وresponsive، العربية RTL/الإنجليزية LTR، POS النقدي، التقارير، والإشعارات المحلية. الإرسال عبر provider والحوادث ما زالا قرارًا مؤجلًا، وليسا فجوة واجهة محلية.

**تحديث 2026-09-15:** أُضيفت رحلة الدفع/الإيصال/الاسترداد المعتمدة، وتقارير العملة/التوقيت والصافي والتفاصيل، وإدارة Reception/Cashier بواسطة مدير الفرع، وتبديل اللغة الفعلي من login والصفحات المحمية. الأدلة الحالية في `.ai/TEST_RESULTS.md`؛ التاريخ الأدنى يصف لقطته الزمنية فقط.

النطاق هو PlayNexus وفق PRD. عناصر marketplace/cart/delivery/storefront المذكورة في قالب الطلب **N/A** وليست نواقص.

## 1. الملخص التنفيذي

الأساس الوظيفي واضح، لكن التجربة تبدو كصفحات CRUD متجاورة وليست مساحة تشغيل يومية. الأسباب الجذرية: صفحة اختيار الفرع تُعامل كـDashboard، صفحات `tickets` و`family-profile` تجمع مهامًا كثيرة، إدارة الوصول موزعة بين أربع وجهات، ومعلومات المنشأة مكررة. تظهر أيضًا مساحات desktop خالية بلا غرض، جداول إدارية ضعيفة على المقاسات الضيقة، وoverflow موثق في pricing mobile.

الهدف: shell واحد، لوحة حسب الدور بعد اختيار الفرع، رحلات قصيرة للبحث والإصدار والمسح، ونقل الإعدادات النادرة إلى tabs/drawers. نعيد استخدام DESIGN.md ومكونات Laravel الحالية؛ لا package تصميم جديد.

أعلى المخاطر: P0 لإثبات حالات loading/error/expired/forbidden قبل الإطلاق؛ P1 لإعادة بنية الرحلات، responsive tables، pricing overflow، وطباعة A5 RTL.

## 2. خريطة الرحلات

| الرحلة | التسلسل المستهدف | الاحتكاك الحالي | النهاية |
|---|---|---|---|
| بدء الوردية | Login → اختيار الفرع → لوحة الدور | `/app` نهاية فارغة | quick actions وحالة اليوم |
| العثور على أسرة | بحث موحد → نتيجة → ملف | سجل منفصل عن التشغيل | ملف + إصدار تذكرة |
| تسجيل أسرة | بحث → لا تطابق → إنشاء → نجاح | الإنشاء وجهة مساوية للبحث | ملف الأسرة الجديدة |
| إصدار تذكرة | أسرة → نوع → تاريخ → مراجعة → إصدار | بحث وإدارة وإنشاء وسجل في صفحة واحدة | تذكرة وطباعة |
| بدء جلسة | scan → preview → confirm → active | تحذير وإدخال ولوحة في كتلة واحدة | جلسة نشطة |
| إدارة موظف | بحث → ملف → دور وفروع → حفظ | staff/roles/assignments متفرقة | وصول مفهوم ومدقق |
| إدارة فرع | قائمة → تفاصيل/ساعات/تشغيل | create/disable/settings متفرقة | ملخص بتبويبات |
| التدقيق | filters → event → details | بيانات خام متعددة الأسطر | من/ماذا/متى/قبل/بعد |

## 3. تحليل الصفحات واللقطات

### مراجعة شخصية لواجهات Desktop

راجعتُ شخصيًا 31 لقطة عربية للديسكتوب: 21 مسارًا أساسيًا و10 اختلافات owner/manager/cashier.

| المسار | القرار الدقيق | الدليل والأثر | P / القبول |
|---|---|---|---|
| `/app` | تسميتها «اختيار مساحة العمل» كبوابة مؤقتة ثم Dashboard حسب الدور | فراغ ضخم وبطاقات فرع فقط | P1؛ أول إجراء خلال نقرة بعد الاختيار |
| families + create | إنشاء الأسرة يظهر بعد no-result؛ deep link يظل متاحًا | البحث والإنشاء مرحلتان متتابعتان | P1؛ البحث أولًا افتراضيًا |
| family profile | Summary + tabs للأطفال/الاستلام/الموافقات/السجل؛ إضافة طفل drawer | لقطة 2294px وثلاث عمليات حساسة | P1؛ above-fold هوية+أطفال+CTA |
| tickets | tabs: إصدار، تحقق، سجل؛ نقل إنشاء النوع إلى Pricing | صفحة >2000px تخلط التشغيل بالإعداد | P1؛ CTA واحد لكل tab |
| sessions | Scan station أعلى وactive grid أسفل؛ إخفاء warning بعد النجاح | وظيفة مترابطة لكن كثافة غير موزونة | P1؛ keyboard flow كامل |
| pricing | أنواع التذاكر هنا؛ create في side panel، cashier read-only | form دائم في عمود ضيق | P1؛ لا overflow 390–1440 |
| staff/create/assignments/roles | Hub باسم Staff & Access: Employees، Roles، Branch access | أربع وجهات لنفس النموذج الذهني | P1؛ موظف→وصول دون بحث ثان |
| branches/manage/settings | قائمة؛ create modal؛ تفاصيل الفرع tabs | التعطيل مجاور لإعدادات يومية | P1؛ confirmation مستقل |
| tenant + settings | دمجهما إلى Settings؛ حذف تكرار جدول الموظفين | Profile يكرر Staff بلا قيمة | P2؛ مصدر واحد للموظفين |
| audit | صف compact + drawer before/after؛ وقت محلي وUTC tooltip | خلايا خام صعبة المسح | P1؛ من/ماذا/متى في صف |
| auth | إبقاء الصفحات؛ language selector مباشر بلا زر تغيير | قرار زائد ومساحة غير لازمة | P2؛ نقرة واحدة وحفظ |
| platform tenants | row action/drawer لتغيير الحالة والسبب | controls دائمة داخل كل صف | P1؛ confirmation + audit |

المشكلات الجذرية: UX-R01 خلط التشغيل بالإعداد؛ UX-R02 تكرار المعلومات؛ UX-R03 landing لا يراعي الدور؛ UX-R04 table-only layouts؛ UX-R05 hierarchy ضعيف؛ UX-R06 غياب أدلة الحالات؛ UX-R07 اتجاه مختلط للأكواد والمال والتاريخ.

السجل الفردي: `audit/auth-shell.md` (120 صورة) و`audit/administration.md` (72 صورة). تدقيق التشغيل شمل 116 PNG وPDF: pricing overflow، 12 capture seams، قرب نص A5 من الحافة، واسم fixture فارغ لا يطابق محتواه. يجب حفظ ledger التشغيل النصي قبل التنفيذ.

## 4. نظام التصميم

- Canvas porcelain، surfaces بيضاء، petrol-teal للتفاعل، semantic colors للحالة فقط.
- Type scale واحد `12/14/16/20/28/36`؛ عنوان صفحة واحد ثم section/body/meta.
- شبكة 4px بقيم `4/8/12/16/24/32/48`؛ لا padding لتعويض فراغ الصفحة.
- Forms بطول 720–880px، والجداول تستعمل العرض المفيد.
- Primary واحد لكل task region؛ Secondary/Ghost/Danger؛ حالات focus/loading/disabled موحدة؛ touch target 44×44.
- Label دائم، help قريب، error تحت الحقل وsummary أعلى النموذج.
- Tables: row actions وresponsive cards بدل horizontal scroll متى أمكن.
- Drawers للإنشاء القصير؛ صفحة كاملة للنماذج الطويلة/الحساسة.
- Status = نص+لون، لا لون فقط.
- RTL عبر logical properties، و`dir=ltr` مع isolation للبريد/PN/المال/timestamp.
- قبول فعلي عند 390،768/820،1024،1366/1440.

## 5. هيكل المعلومات والتنقل

1. التشغيل: Dashboard، العائلات، التذاكر، الجلسات.
2. الإعداد التجاري: التسعير وأنواع التذاكر.
3. الفريق والوصول: الموظفون، الأدوار، وصول الفروع.
4. المنشأة: الفروع، إعدادات المنشأة، التدقيق.

Topbar للفرع واللغة والحساب فقط؛ Sidebar للوجهات حسب الصلاحية. Breadcrumb عند عمق حقيقي فقط. Footer في sidebar داخل scroll flow كي لا يغطي روابط 1024px.

## 6. مصفوفة الحالات

`O` ظاهر، `P` جزئي، `M` غير موجود في الأدلة، `U` غير مختبر، `–` لا ينطبق. 15 سطحًا × 12 حالة = **180 حالة موثقة**.

| السطح | Normal | Loading | Empty | No result | Network | Validation | Stale | Disabled | Confirm | Success | Expired | Forbidden |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Auth | O | M | – | – | M | P | – | U | – | M | P | – |
| Branch gate | O | M | P | – | M | – | M | P | – | M | P | P |
| Families | O | M | O | P | M | P | M | U | U | M | P | P |
| Family create/profile | O | M | P | – | M | P | M | P | P | M | P | P |
| Tickets | O | M | P | P | M | P | M | P | P | M | P | P |
| Sessions | O | M | P | P | M | P | M | P | P | M | P | P |
| Pricing | O | M | P | P | M | P | M | P | U | M | P | P |
| Staff | O | M | P | P | M | P | M | P | P | M | P | P |
| Roles/access | O | M | O | P | M | P | M | P | P | M | P | P |
| Branch admin | O | M | P | P | M | P | M | P | P | M | P | P |
| Tenant | O | M | – | – | M | P | M | P | U | M | P | P |
| Audit | O | M | P | P | M | P | M | U | – | – | P | P |
| Platform | O | M | P | P | M | P | M | P | P | M | P | P |
| Shell/nav | O | M | – | – | M | – | M | P | – | – | P | P |
| Print | O | – | – | – | – | – | – | – | – | – | – | – |

`M` لا يثبت أن التنفيذ غائب؛ يثبت أن الأدلة لا تغطيه.

## 7. Accessibility

Tab order مطابق للتسلسل، skip link وfocus ring؛ accessible names للأيقونات؛ labels مرتبطة؛ errors عبر `aria-describedby` مع focus على summary؛ Dialog بعنوان وfocus trap وEscape وfocus restore؛ WCAG 2.2 AA؛ reflow عند 320px وzoom 200%؛ لا اعتماد على اللون. الاختبارات: keyboard-only، قارئ شاشة عربي/إنجليزي، axe، zoom، dialogs/live regions. مراجع: [Target Size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html)، [ARIA Dialog](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/).

## 8. الأولويات وخريطة التنفيذ

| المرحلة | النطاق | الجهد/الأثر | Definition of Done |
|---|---|---|---|
| 0 إثبات | runtime لكل role/locale/viewport | M/عال | الحالات الحساسة موثقة |
| 1 حرج | pricing، tables، print، raw translation | M/عال | لا overflow/قص/مفاتيح خام |
| 2 تشغيل | branch gate، families، tickets، sessions | L/عال | CTA واحد وtask time مقاس |
| 3 إدارة | Staff & Access، Branches، Tenant | L/عال | لا تكرار ولا تنقل دائري |
| 4 مكونات | shell/forms/tables/drawers/status | M/متوسط | regression عبر 4 viewports/لغتين |
| 5 وصول وأداء | keyboard/SR/zoom/CWV | M/عال | صفر critical axe وAA |

لا تبدأ M4 بإتمام الخطة؛ تحتاج قرار بدء منفصل.

## 9. مؤشرات النجاح

- اختيار الفرع→الإجراء: ≤2 clicks و≤15s.
- بحث الأسرة الناجح ≥95%؛ no-result له CTA.
- إصدار تذكرة median ≤45s؛ بدء جلسة ≤10s و0 duplicate starts.
- إدارة وصول موظف ≤3 دقائق.
- 0 horizontal overflow عند المقاسات الخمسة.
- 0 targets أساسية أقل من 44×44؛ 0 critical/serious a11y.
- p75: LCP≤2.5s، INP≤200ms، CLS≤0.1 وفق [Web Vitals](https://web.dev/articles/vitals).

## 10. Backlog التنفيذي

| ID | المطلوب | P | جهد | تبعية | القبول | الدليل |
|---|---|---|---|---|---|---|
| UX-001 | حفظ ledger التشغيل | P0 | S | QA | 116 PNG+PDF مفهرسة | ops audit |
| UX-002 | استكمال state evidence | P0 | M | data | §6 مثبت runtime | all |
| UX-003 | pricing responsive | P1 | M | layout | 390–1440 بلا overflow | pricing |
| UX-004 | responsive admin rows | P1 | L | table | بيانات وإجراء بلا قص | admin |
| UX-005 | A5 RTL safe area | P1 | S | print CSS | طباعة فعلية بلا قص | PDF |
| UX-006 | branch gate→role dashboard | P1 | M | landing | إجراء خلال نقرة | dashboards |
| UX-007 | family search→create | P1 | S | none | create بعد no-result | families |
| UX-008 | family profile tabs/drawer | P1 | L | drawer | summary+CTA above-fold | profile |
| UX-009 | tickets Issue/Verify/History | P1 | L | tabs | CTA واحد/tab | tickets |
| UX-010 | ticket types→Pricing | P1 | M | UX-009 | cashier بلا setup | tickets/pricing |
| UX-011 | sessions scan station | P1 | M | scanner | keyboard pass | sessions |
| UX-012 | Staff & Access hub | P1 | L | policies | موظف→وصول مباشرة | staff/access |
| UX-013 | بحث staff name/email | P1 | S | scoped query | no-result واضح | assignments |
| UX-014 | role permission groups | P1 | M | matrix | impact warning | roles |
| UX-015 | Branch list/details tabs | P1 | L | table | disable مستقل | branches |
| UX-016 | دمج tenant/profile | P2 | M | nav | لا staff duplication | tenant |
| UX-017 | Audit row+drawer | P1 | M | labels | من/ماذا/متى بصف | audit |
| UX-018 | Platform row action | P1 | M | audit | confirmation+reason | platform |
| UX-019 | language one-step | P2 | S | i18n | حفظ الاختيار | shell/auth |
| UX-020 | sidebar footer flow | P1 | S | shell | آخر رابط ظاهر 1024 | tablet |
| UX-021 | bidi isolation | P1 | M | tokens | نسخ/قراءة سليمان | mixed data |
| UX-022 | form state contract | P1 | M | components | errors/pending موحدة | forms |
| UX-023 | focus-visible | P1 | S | tokens | keyboard visible | all |
| UX-024 | dialog focus | P1 | M | drawer | APG pass | actions |
| UX-025 | empty next actions | P2 | M | copy | لا dead end | empty |
| UX-026 | capture seam/naming | P2 | S | QA | صور/أسماء سليمة | 13 artifacts |
| UX-027 | desktop density | P2 | M | tokens | CTA يحدد خلال 5s | desktop |
| UX-028 | auth parity | P2 | S | i18n | ar/en متطابق | auth |
| UX-029 | role navigation parity | P1 | M | policies | المرئي=المسموح | role set |
| UX-030 | acceptance regression | P0 | L | 001–029 | roles×2 locales×4 sizes | manifests |

## سلامة الأدلة

- HTTP 200 ولقطة لا يثبتان التفاعل أو authorization أو tenant isolation.
- 12 seams في الصور الطويلة مصنفة capture artifact حتى يثبت runtime العكس.
- `tickets/ar-desktop-empty.png` يحتوي تذاكر؛ خلل fixture/name وليس empty-state evidence.
- إلغاء تذكرة غير مستخدمة ليس refund، ولا checkout/payment داخل هذا النطاق.

## 11. مراجعة Desktop الحية بعد موجة التنفيذ الأولى

تاريخ المراجعة: 2026-09-13. راجع المنسق النسخة الفعلية على خادم QA محلي معزول، بحساب owner وبيانات SQLite مؤقتة. شملت المراجعة: branch gate/dashboard، families، family profile، tickets، sessions، pricing، staff، assignments، roles، branches، branch settings، tenant profile/settings، staff create، وaudit. لم تُنفذ عمليات حفظ أو إلغاء أو تغيير صلاحيات.

### ما تحسن فعلًا

- branch gate أصبحت واضحة، وبعد اختيار الفرع تظهر quick actions مناسبة بدل الفراغ السابق.
- تغيير اللغة أصبح إجراءً مباشرًا في topbar.
- Family profile أخفى نماذج التعديل خلف progressive disclosure بدل عرضها كلها.
- Staff أصبح يدعم البحث بالاسم أو البريد، مع انتقال مباشر إلى branch access.
- Roles يشرح مجموعة الصلاحية وحدودها بدل checkbox بلا سياق.
- Sidebar وtopbar ثابتان بصريًا ومتناسقان على desktop.

### قرارات الدمج وإعادة تعريف الهدف

| الحكم | الصفحات | القرار |
|---|---|---|
| دمج كامل | `/app/tenant` + `/app/tenant/settings` | حذف Tenant profile كوجهة مستقلة. صفحة Organization settings تبدأ بملخص الهوية ثم General settings. جدول الموظفين يبقى في Staff فقط. |
| دمج داخل Hub | `/app/staff` + `/app/assignments` + `/app/roles` | وجهة Sidebar واحدة باسم Staff & Access. داخلها Employees وRoles. فتح الموظف يعرض Status وBranch access وRole assignments في سياق واحد. |
| دمج وظيفي | Ticket types داخل `/app/tickets` مع `/app/pricing` | نقل إنشاء وإدارة Ticket types إلى Pricing. Tickets صفحة تشغيل فقط: Validate، Issue، History. |
| إبقاء مع تبويبات | `/app/branches/manage` + `/app/branches/{id}/settings` | تبقى قائمة الفروع نقطة الدخول؛ تفاصيل الفرع تنقسم Overview، Operations، Opening hours. لا تُحمّل كل الإعدادات في صفحة طويلة واحدة. |
| إبقاء منفصل | Families registry وFamily profile | البحث يحتاج شاشة سريعة، والملف يحتاج سياق أسرة. الربط بينهما صحيح ولا يُدمجان. |
| إبقاء منفصل | Tickets وLive sessions | التذكرة entitlement، والجلسة حالة تشغيل حية. دمجهما سيخلط الإصدار بالاستهلاك. تُضاف روابط سياقية فقط. |

### صفحات هدفها غير واضح أو ضعيف

1. **Tenant profile:** لا يقدم مهمة مستقلة؛ يعرض اسم المنشأة وجدول الموظفين المكرر. القرار: إزالته كصفحة مستقلة.
2. **Roles:** مع صلاحية واحدة فقط، الصفحة تبدو proof-of-concept. يجب أن يكون هدفها «تعريف دور قابل لإعادة الاستخدام» مع عدد الموظفين والفروع المتأثرة وpreview للأثر.
3. **Audit:** يعرض `Unknown action` و`Unknown reason` وUTC خامًا؛ المستخدم لا يستطيع فهم الحدث. يجب أن يجيب الصف عن: من، ماذا غيّر، على أي سجل، متى محليًا، والنتيجة؛ التفاصيل في drawer.
4. **Dashboard:** تحسن، لكن Workspace context يكرر الفرع والدور الموجودين في topbar. يجب استبداله بأشياء تحتاج انتباهًا فعلًا: جلسات قاربت الانتهاء، السعة، وتذاكر تحتاج إجراء، عندما تتوفر هذه البيانات ضمن المرحلة.
5. **Business profile:** واضح كنموذج لكنه صغير جدًا كصفحة مستقلة؛ مكانه الطبيعي tab داخل Organization settings.

### صفحات تحتاج إعادة بناء أفضل

#### Tickets، P1

تجمع حاليًا Validate، filters، Issue، Ticket types، Issued tickets في صفحة واحدة طويلة. الإجراء التشغيلي يتنافس مع الإعداد الإداري.

التصميم المطلوب:

- segmented tabs أعلى المحتوى: `Issue ticket`، `Validate ticket`، `Ticket history`.
- scanner input يظل أول focus داخل Validate فقط.
- Issue يعرض family search/selection ثم ticket type/date ثم price summary وCTA.
- Ticket types يُنقل إلى Pricing.
- سجل التذاكر table compact؛ التفاصيل والإلغاء والتصحيح في expandable row أو drawer.

#### Family profile، P1

progressive disclosure حسن الصفحة، لكن relationships وإجراءات السحب والإلغاء ما زالت تسيطر على المحتوى، وAdd child panel ينافس بيانات الأسرة.

التصميم المطلوب:

- header summary ثابت: guardian، masked phone، عدد الأطفال، safety flag.
- tabs: Children، Authorized pickup، Consents، Activity.
- Add child زر في Children يفتح disclosure داخل نفس المسار.
- destructive actions داخل منطقة Danger منفصلة، لا وسط سجل الطفل.
- لا تعرض نموذج ربط ولي أمر حتى يختار المستخدم «إضافة شخص مصرح».

#### Staff، P1

كل صف يحتوي select وزر Save دائمين، فيتحول الجدول إلى سلسلة نماذج طويلة. أزرار Branch access وRoles أعلى الصفحة تبدو وجهات متساوية بدل أجزاء من إدارة الموظف.

التصميم المطلوب:

- صف الموظف: الاسم، البريد، status، role summary، branches count، menu.
- تغيير status في confirmation صغير من قائمة الصف.
- النقر على الموظف يفتح detail page/panel لإدارة الدور والفروع معًا.
- Add staff account يبقى CTA الأساسي، والبحث يسبقه في ترتيب Tab.

#### Pricing، P1

جدول القواعد صغير أمام form دائم وطويل. إنشاء rule ليس المهمة الأكثر تكرارًا.

التصميم المطلوب:

- قائمة القواعد هي السطح الأساسي مع version/status/branch/price.
- `Create pricing rule` يفتح side panel أو صفحة مخصصة.
- Fixed timing terms تظهر summary غير editable مرتبطة بالفرع.
- Ticket types تصبح tab ثانية هنا.

#### Branch settings، P1

صفحة طويلة تجمع الهوية والضريبة والدفع والسعة وساعات الأسبوع. زر الحفظ بعيد عن الحقول الأولى.

التصميم المطلوب:

- tabs: Overview، Operations، Opening hours.
- Save bar ثابتة داخل المحتوى عند وجود تغييرات.
- Opening hours تدعم copy-to-days وweekdays/weekend presets لتقليل الإدخال.
- تعطيل الفرع يبقى في Branch management مع confirmation وأثر واضح.

### تعديلات أصغر مهمة

- Families: زر Add family أعلى الصفحة ما زال ينافس مبدأ «ابحث أولًا». اجعله Secondary، وأظهر CTA الأقوى بعد no-result.
- Branch management: Create branch form الدائم يزاحم القائمة؛ استبدله بزر يفتح inline disclosure. إجراءات التعطيل تنتقل إلى row menu ثم confirmation.
- Assignments: البحث واختيار الموظف خطوتان متتاليتان ظاهرتان معًا. بعد نتيجة البحث، اختر الموظف مباشرة أو اعرض نتائج قابلة للنقر، ثم جدول الوصول.
- Staff create: نموذج واضح، لكنه يحتاج بعد النجاح next step مباشر «Assign branch and role».
- Sessions: الترتيب جيد، لكن filters أكبر من live board. اجعل Scan station أولًا للكاشير، واجعل board/filters أولًا للمدير حسب الدور.
- Navigation: ما زال Sidebar يعرض 12 وجهة. بعد الدمج يصبح 8 تقريبًا: Dashboard/Branch، Families، Tickets، Sessions، Pricing، Staff & Access، Organization، Audit.
- Auth: صفحة الدخول نظيفة؛ لا تحتاج إعادة تصميم كبيرة.

### أجزاء مفقودة فعليًا من تجربة Desktop

- حالات loading skeleton لكل القوائم.
- network/server error مع Retry يحافظ على المدخلات.
- session expired يعيد المستخدم بعد الدخول إلى الصفحة/السياق السابق.
- permission denied يشرح المطلوب دون كشف سجل خارج النطاق.
- unsaved changes warning في Branch وOrganization settings.
- success state يحتوي reference/next action، وليس toast فقط.
- confirmations موحدة للتعطيل، سحب الموافقة، إلغاء الوصول، وإلغاء التذكرة.
- لا توجد أدلة بصرية كافية لحالات empty/no-result في tickets، sessions، pricing، audit، roles، وbranches.
- role impact preview: عدد الموظفين والفروع المتأثرة قبل حفظ تغيير الصلاحية.
- audit detail view للـbefore/after وrequest correlation.
- Dashboard operational alerts الحالية غائبة؛ الموجود quick links وليس attention dashboard كاملًا.

### ملاحظة التكرار في اللقطات الطويلة

أثبت فحص DOM أن تكرار headings/forms الظاهر في بعض full-page screenshots ليس تكرارًا حقيقيًا في الصفحة. سببه stitching مع عناصر shell الثابتة. لذلك لا يُفتح bug في Blade بسبب الصورة وحدها؛ يُصلح capture harness بإخفاء/تثبيت shell بطريقة مناسبة أثناء full-page capture، وتظل لقطات viewport العادية مرجع الحكم البصري.

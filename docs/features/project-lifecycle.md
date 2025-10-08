# Жизненный цикл проекта

## Контекст страницы `src/project/index.php`
Страница проекта подключает защищённый bootstrap, загружает агрегированную информацию из API `projects/data.php` и расширяет контент виджетами для аудита, управления клиентами/локациями, рекрутинга, ассетов и финансов.【F:src/project/index.php†L2-L151】【F:src/project/project_index.twig†L1-L200】【F:src/api/projects/data.php†L1-L303】

## Типовой сценарий работы пользователя
1. Менеджер проекта открывает страницу, чтобы проверить текущее состояние карточки: статус, тип, привязку к клиенту/площадке и ближайшие даты. При необходимости он сразу меняет клиента или площадку через встроенные селекторы, что вызывает соответствующие API и фиксирует действие в аудите.【F:src/project/project_index.twig†L154-L200】【F:src/api/projects/changeClient.php†L4-L19】【F:src/api/projects/changeVenue.php†L4-L23】
2. Далее менеджер сверяется с блоком рекрутинга `projectsVacantRoles`: актуализирует квоты ролей, просматривает заявки, принимает или отклоняет кандидатов и отслеживает заполненность команды, используя связанные API из `crew/crewRoles`.【F:src/project/index.php†L68-L77】【F:src/api/projects/crew/crewRoles/list.php†L6-L31】【F:src/api/projects/crew/crewRoles/accept.php†L6-L33】
3. После проверки команды менеджер переходит к доске ассигнований, чтобы увидеть, какие ассеты уже зарезервированы или требуют выпуска, обновляет статусы и контролирует блокирующие предупреждения перед отгрузкой. При необходимости он синхронизирует данные с живой доской через API `assets/statusList.php`.【F:src/project/index.php†L79-L118】【F:src/api/projects/assets/statusList.php†L4-L46】
4. Финансовый раздел позволяет менеджеру вносить новые платежи, сверять кеш и замечать несоответствия. Дополнительно он обновляет заметки и прикрепляет документы, чтобы сохранить прозрачную историю проекта для команды и аудита.【F:src/api/projects/data.php†L40-L247】【F:src/api/projects/newPayment.php†L1-L40】【F:src/api/projects/newNote.php†L4-L16】
5. Завершая работу, менеджер просматривает журнал аудита и вкладку истории, убеждается, что под-проекты синхронизированы по статусам, и планирует следующие шаги по чек-листам жизненного цикла, прежде чем перейти к следующему проекту.【F:src/project/index.php†L7-L16】【F:src/project/index.php†L120-L148】

### Основные данные и карточка проекта
* `projects/data.php` собирает ключевые поля проекта вместе с типом, статусом, клиентом, локацией и менеджером, а также список под-проектов.【F:src/api/projects/data.php†L19-L36】
* Карточка в `project_index.twig` отображает название, описание, карточки клиента, менеджера и площадки. Для пользователей с правами доступны селекторы Select2 для изменения клиента, менеджера и адреса прямо с карточки.【F:src/project/project_index.twig†L142-L200】

### Аудит и история
* `index.php` подгружает журнал аудита проекта с автором и временными метками, чтобы вкладка «Comments & History» отображала события и комментарии.【F:src/project/index.php†L7-L16】【F:src/project/project_index.twig†L112-L117】

### Управление клиентом и локацией
* При наличии прав запрашиваются списки клиентов и иерархия локаций с учётом архивных записей, чтобы позволить перевыбор клиента или площадки проекта и автоматически включать текущую, даже если она заархивирована.【F:src/project/index.php†L18-L66】
* API `changeClient.php` и `changeVenue.php` обновляют связь проекта с клиентом и площадкой, фиксируя изменения в журнале аудита.【F:src/api/projects/changeClient.php†L4-L19】【F:src/api/projects/changeVenue.php†L4-L23】

### Рекрутинг и `projectsVacantRoles`
* Страница собирает открытые вакансии проекта с учётом дедлайнов, квот и ограничений по группам, формируя виджет рекрутинга для пользователей с соответствующими правами.【F:src/project/index.php†L68-L77】
* API из директории `crew/crewRoles` управляют жизненным циклом вакансий: создание/редактирование ролей, просмотр и модерация заявок, автопринятие по принципу «кто первый», а также уведомления при назначении.【F:src/api/projects/crew/crewRoles/edit.php†L7-L63】【F:src/api/projects/crew/crewRoles/list.php†L6-L31】【F:src/api/projects/crew/crewRoles/apply.php†L12-L60】【F:src/api/projects/crew/crewRoles/accept.php†L6-L33】

### Доска ассигнований и статусы отгрузки
* Контроллер рассчитывает структуру `BOARDASSETS`/`BOARDSTATUSES`, группируя назначенные ассеты по статусам как для текущей инстанции, так и для под-проектов, что питает доску отгрузки и вкладку «Asset Dispatch».【F:src/project/index.php†L79-L118】
* Дополнительный API `assets/statusList.php` возвращает такую же иерархию для живой доски, повторно используя `projects/data.php`.【F:src/api/projects/assets/statusList.php†L4-L46】
* В интерфейсе показываются предупреждения по блокирующим флагам обслуживания и статусу «Assets Released».【F:src/project/project_index.twig†L73-L90】

### Под-проекты и шаблоны типов/статусов
* `projects/data.php` загружает дочерние проекты с их статусами, а `index.php` проверяет возможность создавать новые под-проекты на основе лимита инстанции.【F:src/api/projects/data.php†L31-L36】【F:src/project/index.php†L147-L148】
* Страница подготавливает списки допустимых статусов и типов проекта, чтобы предоставить редакторам быстрый доступ к шаблонам статуса и типизации.【F:src/project/index.php†L120-L132】
* API `changeStatus.php`, `followParentStatus.php` и `changeProjectType.php` поддерживают управление статусами, синхронизацией статусов с родителем и сменой типа проекта с проверками на конфликт ассетов.【F:src/api/projects/changeStatus.php†L1-L67】【F:src/api/projects/changeStatus.php†L70-L104】【F:src/api/projects/changeProjectType.php†L4-L20】
* API `changeSubProject.php` меняет родителя проекта и ведёт журнал изменений.【F:src/api/projects/changeSubProject.php†L4-L24】

### Финансы, файлы и заметки
* Финансовый блок подсчитывает платежи по категориям, стоимость и массу ассетов, скидки и формирует кеш, а также предоставляет форматированные значения для UI вкладки «Finance».【F:src/api/projects/data.php†L40-L247】
* API `newPayment.php` и `deletePayment.php` управляют движением платежей, используя `projectFinanceCacher` для поддержки согласованности кеша и аудита.【F:src/api/projects/newPayment.php†L1-L40】【F:src/api/projects/deletePayment.php†L7-L28】
* `projects/data.php` собирает заметки, состав команды и связанные файлы/документы, формируя содержимое вкладок «Notes», «Crew» и файловых списков.【F:src/api/projects/data.php†L268-L288】
* API `newNote.php` и `editNote.php` добавляют и обновляют записи заметок с очисткой текста и аудитом.【F:src/api/projects/newNote.php†L4-L16】【F:src/api/projects/editNote.php†L4-L23】

## Ключевые API для работы с жизненным циклом проекта
### Обновление параметров и графика
* `changeName.php`, `changeDescription.php` и `changeProjectDates.php` позволяют обновлять базовые параметры карточки проекта; все они проверяют права и фиксируют изменения в аудите.【F:src/api/projects/changeName.php†L4-L13】【F:src/api/projects/changeDescription.php†L4-L13】【F:src/api/projects/changeProjectDates.php†L4-L13】
* `changeDeliveryNotes.php`, `changeInvoiceNotes.php` и `changeProjectDeliverDates.php` (не показаны выше) работают аналогично и применяются при выпуске документов по проекту.

### Управление ассетами
* Эндпоинты из `projects/assets/` покрывают назначение, переоценку, скидки и смену статусов отдельных единиц оборудования, поддерживая синхронизацию с доской отгрузки и уведомлениями об ограничениях.【F:src/api/projects/assets/statusList.php†L4-L46】

### Финансы и документы
* Помимо платежей, `projects/data.php` агрегирует ссылки на файлы (контракты, счета, накладные) из S3, чтобы вкладка «Files, Notes, Invoices & Quotes» могла отображать и скачивать документы.【F:src/api/projects/data.php†L282-L288】

### Команда и рекрутинг
* Поддиректория `crew/` предоставляет поиск пользователей, назначение/снятие членов команды и сортировку рангов, поддерживая тот же набор прав, что и виджеты на странице.【F:src/api/projects/crew/crewRoles/list.php†L6-L31】【F:src/api/projects/crew/assign.php†L4-L128】

## Чек-листы по этапам жизненного цикла
### Инициация
1. Создайте проект с корректным типом и статусом шаблона; при необходимости переключите тип через `changeProjectType.php`.【F:src/project/index.php†L120-L132】【F:src/api/projects/changeProjectType.php†L4-L20】
2. Убедитесь, что выбраны клиент, менеджер и площадка, используя встроенные селекторы или соответствующие API.【F:src/project/project_index.twig†L154-L200】【F:src/api/projects/changeClient.php†L4-L19】【F:src/api/projects/changeVenue.php†L4-L23】
3. Зафиксируйте базовое описание и временной интервал проекта через API обновления описания и дат.【F:src/api/projects/changeDescription.php†L4-L13】【F:src/api/projects/changeProjectDates.php†L4-L13】

### Планирование
1. Настройте под-проекты и иерархию, проверив лимит на создание и установив синхронизацию статусов при необходимости.【F:src/project/index.php†L147-L148】【F:src/api/projects/changeSubProject.php†L4-L24】【F:src/api/projects/changeStatus.php†L70-L104】
2. Сформируйте вакансии `projectsVacantRoles`, задав квоты, дедлайны и группы доступа через API редактирования ролей.【F:src/api/projects/crew/crewRoles/edit.php†L7-L63】
3. Сконфигурируйте шаблоны статусов и типы, чтобы чётко обозначить контрольные точки отгрузки и закрытия проекта.【F:src/project/index.php†L120-L132】

### Исполнение
1. Отслеживайте заполнение ролей и обрабатывайте заявки через список заявок и действия accept/reject, чтобы оперативно комплектовать команду.【F:src/api/projects/crew/crewRoles/list.php†L6-L31】【F:src/api/projects/crew/crewRoles/accept.php†L6-L33】
2. Управляйте движением ассетов на доске: обновляйте статусы, следите за блокирующими флагами и предупреждениями об освобождённых ресурсах.【F:src/project/index.php†L79-L118】【F:src/project/project_index.twig†L73-L104】
3. Ведите финансовый контроль: регистрируйте продажи, субаренду и выплаты, сверяйте кеш и распределённую стоимость/массу ассетов.【F:src/api/projects/data.php†L40-L247】【F:src/api/projects/newPayment.php†L1-L40】
4. Документируйте ход работ в заметках и храните файлы/накладные в соответствующих разделах.【F:src/api/projects/data.php†L268-L288】【F:src/api/projects/newNote.php†L4-L16】

### Закрытие
1. Проверьте, что статусы проекта и под-проектов переведены в завершающее состояние без конфликтов ассетов; при необходимости используйте API смены статуса и проверки конфликтов с назначениями.【F:src/api/projects/changeStatus.php†L14-L69】
2. Подтвердите, что все платежи учтены и кассовый остаток сверен, затем очистите или заархивируйте лишние записи в соответствии с политикой инстанции.【F:src/api/projects/data.php†L178-L215】【F:src/api/projects/deletePayment.php†L7-L28】
3. Финализируйте документацию (накладные, счета, заметки) и убедитесь, что журнал аудита отражает ключевые действия перед архивированием проекта.【F:src/project/index.php†L7-L16】【F:src/api/projects/data.php†L282-L288】

## Рекомендации по мониторингу прогресса
* Регулярно просматривайте вкладку «Comments & History», чтобы отслеживать изменения в статусах, финансах и рекрутинге без обращения к базе данных напрямую.【F:src/project/index.php†L7-L16】【F:src/project/project_index.twig†L112-L117】
* Используйте доску ассигнований как Kanban для статусов отгрузки, сверяясь с API `statusList.php` для интеграции во внешние панели мониторинга.【F:src/project/index.php†L79-L118】【F:src/api/projects/assets/statusList.php†L4-L46】
* Настройте проверки на несоответствие кеша финансов (флаг `projectFinancesCacheMismatch`) и реагируйте на предупреждения, чтобы избежать расхождений между фактом и отчётностью.【F:src/api/projects/data.php†L200-L251】
* Включите автоматические уведомления при заявках и изменениях ролей, чтобы менеджеры оперативно реагировали на изменения в команде.【F:src/api/projects/crew/crewRoles/apply.php†L36-L60】

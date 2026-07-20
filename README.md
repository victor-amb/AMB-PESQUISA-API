
```
AMB-PESQUISAS
├─ .editorconfig
├─ .npmrc
├─ app
│  ├─ Http
│  │  ├─ Controllers
│  │  │  ├─ Api
│  │  │  │  ├─ AuthController.php
│  │  │  │  ├─ DashboardController.php
│  │  │  │  ├─ InvitationController.php
│  │  │  │  ├─ SearchAnswerController.php
│  │  │  │  ├─ SearchController.php
│  │  │  │  ├─ SpecialtyController.php
│  │  │  │  └─ UserController.php
│  │  │  └─ Controller.php
│  │  └─ Requests
│  │     ├─ LoginRequest.php
│  │     ├─ StoreSearchAnswerRequest.php
│  │     ├─ StoreSearchRequest.php
│  │     ├─ StoreSpecialtyRequest.php
│  │     ├─ StoreUserRequest.php
│  │     ├─ UpdateProfileRequest.php
│  │     ├─ UpdateSearchRequest.php
│  │     └─ UpdateUserRequest.php
│  ├─ Mail
│  │  ├─ CustomInvitationMail.php
│  │  └─ PlatformInvitationMail.php
│  ├─ Models
│  │  ├─ Responder.php
│  │  ├─ Search.php
│  │  ├─ SearchAnswer.php
│  │  ├─ SearchInvitation.php
│  │  ├─ Specialty.php
│  │  ├─ SystemInvitation.php
│  │  └─ User.php
│  └─ Providers
│     └─ AppServiceProvider.php
├─ artisan
├─ bootstrap
│  ├─ app.php
│  ├─ cache
│  │  ├─ packages.php
│  │  └─ services.php
│  └─ providers.php
├─ composer.json
├─ composer.lock
├─ config
│  ├─ app.php
│  ├─ auth.php
│  ├─ cache.php
│  ├─ database.php
│  ├─ filesystems.php
│  ├─ logging.php
│  ├─ mail.php
│  ├─ queue.php
│  ├─ sanctum.php
│  ├─ services.php
│  └─ session.php
├─ database
│  ├─ database.sqlite
│  ├─ factories
│  │  └─ UserFactory.php
│  ├─ migrations
│  │  ├─ 2026_07_03_125653_create_base_laravel_tables.php
│  │  ├─ 2026_07_03_125726_create_specialties_table.php
│  │  ├─ 2026_07_03_125824_create_users_table.php
│  │  ├─ 2026_07_03_125835_create_responders_table.php
│  │  ├─ 2026_07_03_125908_create_searches_table.php
│  │  ├─ 2026_07_03_125916_create_invitations_tables.php
│  │  ├─ 2026_07_03_125930_create_search_answers_table.php
│  │  └─ 2026_07_13_145127_add_confirmed_data_to_responders_table.php
│  └─ seeders
│     ├─ DatabaseSeeder.php
│     ├─ SpecialtySeeder.php
│     └─ UserSeeder.php
├─ package.json
├─ phpunit.xml
├─ public
│  ├─ .htaccess
│  ├─ favicon.ico
│  ├─ index.php
│  └─ robots.txt
├─ README.md
├─ resources
│  ├─ css
│  │  └─ app.css
│  ├─ js
│  │  └─ app.js
│  └─ views
│     └─ welcome.blade.php
├─ routes
│  ├─ api.php
│  ├─ console.php
│  └─ web.php
├─ storage
│  ├─ app
│  │  ├─ private
│  │  └─ public
│  ├─ framework
│  │  ├─ cache
│  │  │  └─ data
│  │  ├─ sessions
│  │  ├─ testing
│  │  └─ views
│  └─ logs
├─ tests
│  ├─ Feature
│  │  ├─ AuthTest.php
│  │  ├─ ExampleTest.php
│  │  ├─ InvitationTest.php
│  │  ├─ SearchAnswerTest.php
│  │  ├─ SearchTest.php
│  │  ├─ SpecialtyTest.php
│  │  └─ UserManagementTest.php
│  ├─ TestCase.php
│  └─ Unit
│     └─ ExampleTest.php
└─ vite.config.js

```
# E-commerce Platform

Hybrid e-commerce platform: a Laravel admin panel and a Nest.js API gateway sharing one PostgreSQL database.

```
ecommerce-platform/
├── admin-service/       # Laravel (admin panel)
├── api-gateway/         # Nest.js (API gateway + core API)
├── database/schema/     # Reference SQL schema, one file per table
└── docker-compose.yml   # PostgreSQL, Redis, Mailpit
```

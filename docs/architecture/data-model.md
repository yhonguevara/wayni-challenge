# Data Model

## Importer DB: `import_logs` Table

```sql
CREATE TABLE import_logs (
    id             BIGSERIAL PRIMARY KEY,
    filename       VARCHAR(255)   NOT NULL,
    status         VARCHAR(20)    NOT NULL DEFAULT 'pending',  -- pending|processing|done|failed
    total_lines    INTEGER,
    total_deudores INTEGER,
    total_entidades INTEGER,
    duration_ms    INTEGER,
    error_message  TEXT,
    started_at     TIMESTAMPTZ,
    finished_at    TIMESTAMPTZ,
    created_at     TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);
```

---

## Query DB: `deudores` Table (Read Model)

```sql
CREATE TABLE deudores (
    id                   BIGSERIAL PRIMARY KEY,
    nro_identificacion   VARCHAR(11)    NOT NULL UNIQUE,  -- CUIT/CUIL without dashes
    situacion_maxima     VARCHAR(2)     NOT NULL,          -- Values: '01', '03', '04', '05', '11', '21', '23'
    suma_total_prestamos NUMERIC(18, 2) NOT NULL DEFAULT 0,
    created_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_deudores_situacion ON deudores(situacion_maxima);
CREATE INDEX idx_deudores_suma      ON deudores(suma_total_prestamos DESC);
```

**Note:** `situacion_maxima` is `VARCHAR(2)` because codes are '01', '03', '04', '05', '11', '21', '23' (not simple numerics).

---

## Query DB: `entidades` Table (Read Model)

```sql
CREATE TABLE entidades (
    id                   BIGSERIAL PRIMARY KEY,
    codigo_entidad       VARCHAR(5)     NOT NULL UNIQUE,
    suma_total_prestamos NUMERIC(18, 2) NOT NULL DEFAULT 0,
    created_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_entidades_suma ON entidades(suma_total_prestamos DESC);
```

---

## Eloquent Models

### Importer Service

```
app/
└── Domain/
    └── Import/
        └── ImportLog.php        → table: import_logs
```

### Query API

```
app/
└── Domain/
    ├── Deudor/
    │   └── Deudor.php           → table: deudores
    └── Entity/
        └── Entity.php           → table: entidades
```

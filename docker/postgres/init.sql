-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Set default UUID generation
ALTER DATABASE employee_db SET default_with_oids = false;

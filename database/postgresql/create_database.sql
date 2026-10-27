-- Ejecutar conectado a una base administrativa, por ejemplo "postgres".
-- La cuenta usada debe tener permiso CREATEDB.
SELECT 'CREATE DATABASE bodyevolutiongym'
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'bodyevolutiongym'
)\gexec

-- RutaH&D2 · SOLO UNA VEZ, tras ejecutar 13_catalogos_comerciales_aditivos.sql.
-- NO ejecutado en InfinityFree. Respaldar productos y revisar la tabla antes de aplicar.
-- Mantiene intactos categoria_id y categorias_productos existentes.
-- Las columnas nuevas son NULL para que productos antiguos no pierdan registros.
ALTER TABLE productos
 ADD COLUMN rubro_id BIGINT UNSIGNED NULL,
 ADD COLUMN departamento_id BIGINT UNSIGNED NULL,
 ADD COLUMN familia_id BIGINT UNSIGNED NULL,
 ADD COLUMN linea_id BIGINT UNSIGNED NULL,
 ADD COLUMN marca_id BIGINT UNSIGNED NULL,
 ADD COLUMN modelo_id BIGINT UNSIGNED NULL,
 ADD COLUMN tipo_unidad_id BIGINT UNSIGNED NULL,
 ADD COLUMN gramaje_id BIGINT UNSIGNED NULL,
 ADD COLUMN tipo_envasado_id BIGINT UNSIGNED NULL,
 ADD COLUMN condicion_tributaria_id BIGINT UNSIGNED NULL,
 ADD KEY ix_productos_rubro (rubro_id),
 ADD KEY ix_productos_departamento (departamento_id),
 ADD KEY ix_productos_familia (familia_id),
 ADD KEY ix_productos_linea (linea_id),
 ADD KEY ix_productos_marca (marca_id),
 ADD KEY ix_productos_modelo (modelo_id),
 ADD KEY ix_productos_tipo_unidad (tipo_unidad_id),
 ADD KEY ix_productos_gramaje (gramaje_id),
 ADD KEY ix_productos_tipo_envasado (tipo_envasado_id),
 ADD KEY ix_productos_condicion_tributaria (condicion_tributaria_id),
 ADD CONSTRAINT fk_hd_prod_rubro FOREIGN KEY(rubro_id) REFERENCES rubros(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_depto FOREIGN KEY(departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_familia FOREIGN KEY(familia_id) REFERENCES familias(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_linea FOREIGN KEY(linea_id) REFERENCES lineas(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_marca FOREIGN KEY(marca_id) REFERENCES marcas(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_modelo FOREIGN KEY(modelo_id) REFERENCES modelos(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_unidad FOREIGN KEY(tipo_unidad_id) REFERENCES tipos_unidad(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_gramaje FOREIGN KEY(gramaje_id) REFERENCES gramajes(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_envasado FOREIGN KEY(tipo_envasado_id) REFERENCES tipos_envasado(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_hd_prod_tributaria FOREIGN KEY(condicion_tributaria_id) REFERENCES condiciones_tributarias(id) ON DELETE RESTRICT;
-- El API deberá validar que departamento pertenece a rubro,
-- familia a departamento, línea a familia y modelo a marca.
-- No conectar los CRUD al MySQL real antes de autorización y autenticación.

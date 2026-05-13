/* SE USA PARA INSERTAR A LA BASE NUEVOS SERVICIOS OFRECIDOS */
INSERT INTO `tipos_servicio`(
    `id_tipo_servicio`,
    `nombre`,
    `descripcion`,
    `estado`
)
VALUES(
    NULL,
    'Diagnóstico',
    'Se está en la espera de lo que diga el cliente',
    '1'
);



/* SE USA PARA INSERTAR A LA BASE NUEVOS ESTADOS DE LAS ORDENES */

INSERT INTO `orden_estados`(
    `id_orden_estado`,
    `nombre_estado`,
    `estado`
)
VALUES(NULL, 'En proceso', '1');




/* Datos de la empresa, registrados por defecto */
INSERT INTO `empresa`(
    `id_empresa`,
    `nombre_empresa`,
    `slogan`,
    `leyenda1`,
    `leyenda2`,
    `iva`
)
VALUES(
    NULL,
    'REBALLING GUAYAQUIL ECUADOR',
    'EXPERTOS EN REBALLING Y ELECTRÓNICA',
    'SI EL EQUIPO NO ES RETIRADO DENTRO DE 60 DÍAS DESPUÉS DE HABER DEJADO PARA SU REPARACIÓN, PASARÁ AUTOMÁTICAMENTE A REMATE, PERDIENDO TODO SU DERECHO A RECLAMO ALGUNO, NO NOS RESPONSABILIZAMOS POR OBJETOS OLVIDADOS EN SUS EQUIPOS ASÍ COMO LOS QUE NO CONSTEN EN ESTE COMPROBANTE',
    'leyenda2',
    0.15
);
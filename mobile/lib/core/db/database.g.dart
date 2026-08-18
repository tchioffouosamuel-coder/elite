// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'database.dart';

// ignore_for_file: type=lint
class $AnneeScolairesTable extends AnneeScolaires
    with TableInfo<$AnneeScolairesTable, AnneeScolaire> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $AnneeScolairesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _libelleMeta = const VerificationMeta(
    'libelle',
  );
  @override
  late final GeneratedColumn<String> libelle = GeneratedColumn<String>(
    'libelle',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _dateDebutMeta = const VerificationMeta(
    'dateDebut',
  );
  @override
  late final GeneratedColumn<String> dateDebut = GeneratedColumn<String>(
    'date_debut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dateFinMeta = const VerificationMeta(
    'dateFin',
  );
  @override
  late final GeneratedColumn<String> dateFin = GeneratedColumn<String>(
    'date_fin',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _isActiveMeta = const VerificationMeta(
    'isActive',
  );
  @override
  late final GeneratedColumn<bool> isActive = GeneratedColumn<bool>(
    'is_active',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("is_active" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    libelle,
    dateDebut,
    dateFin,
    isActive,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'annee_scolaires';
  @override
  VerificationContext validateIntegrity(
    Insertable<AnneeScolaire> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('libelle')) {
      context.handle(
        _libelleMeta,
        libelle.isAcceptableOrUnknown(data['libelle']!, _libelleMeta),
      );
    } else if (isInserting) {
      context.missing(_libelleMeta);
    }
    if (data.containsKey('date_debut')) {
      context.handle(
        _dateDebutMeta,
        dateDebut.isAcceptableOrUnknown(data['date_debut']!, _dateDebutMeta),
      );
    }
    if (data.containsKey('date_fin')) {
      context.handle(
        _dateFinMeta,
        dateFin.isAcceptableOrUnknown(data['date_fin']!, _dateFinMeta),
      );
    }
    if (data.containsKey('is_active')) {
      context.handle(
        _isActiveMeta,
        isActive.isAcceptableOrUnknown(data['is_active']!, _isActiveMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  AnneeScolaire map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return AnneeScolaire(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      libelle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}libelle'],
      )!,
      dateDebut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_debut'],
      ),
      dateFin: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_fin'],
      ),
      isActive: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}is_active'],
      )!,
    );
  }

  @override
  $AnneeScolairesTable createAlias(String alias) {
    return $AnneeScolairesTable(attachedDatabase, alias);
  }
}

class AnneeScolaire extends DataClass implements Insertable<AnneeScolaire> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final String libelle;
  final String? dateDebut;
  final String? dateFin;
  final bool isActive;
  const AnneeScolaire({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    required this.libelle,
    this.dateDebut,
    this.dateFin,
    required this.isActive,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    map['libelle'] = Variable<String>(libelle);
    if (!nullToAbsent || dateDebut != null) {
      map['date_debut'] = Variable<String>(dateDebut);
    }
    if (!nullToAbsent || dateFin != null) {
      map['date_fin'] = Variable<String>(dateFin);
    }
    map['is_active'] = Variable<bool>(isActive);
    return map;
  }

  AnneeScolairesCompanion toCompanion(bool nullToAbsent) {
    return AnneeScolairesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      libelle: Value(libelle),
      dateDebut: dateDebut == null && nullToAbsent
          ? const Value.absent()
          : Value(dateDebut),
      dateFin: dateFin == null && nullToAbsent
          ? const Value.absent()
          : Value(dateFin),
      isActive: Value(isActive),
    );
  }

  factory AnneeScolaire.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return AnneeScolaire(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      libelle: serializer.fromJson<String>(json['libelle']),
      dateDebut: serializer.fromJson<String?>(json['dateDebut']),
      dateFin: serializer.fromJson<String?>(json['dateFin']),
      isActive: serializer.fromJson<bool>(json['isActive']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'libelle': serializer.toJson<String>(libelle),
      'dateDebut': serializer.toJson<String?>(dateDebut),
      'dateFin': serializer.toJson<String?>(dateFin),
      'isActive': serializer.toJson<bool>(isActive),
    };
  }

  AnneeScolaire copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    String? libelle,
    Value<String?> dateDebut = const Value.absent(),
    Value<String?> dateFin = const Value.absent(),
    bool? isActive,
  }) => AnneeScolaire(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    libelle: libelle ?? this.libelle,
    dateDebut: dateDebut.present ? dateDebut.value : this.dateDebut,
    dateFin: dateFin.present ? dateFin.value : this.dateFin,
    isActive: isActive ?? this.isActive,
  );
  AnneeScolaire copyWithCompanion(AnneeScolairesCompanion data) {
    return AnneeScolaire(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      libelle: data.libelle.present ? data.libelle.value : this.libelle,
      dateDebut: data.dateDebut.present ? data.dateDebut.value : this.dateDebut,
      dateFin: data.dateFin.present ? data.dateFin.value : this.dateFin,
      isActive: data.isActive.present ? data.isActive.value : this.isActive,
    );
  }

  @override
  String toString() {
    return (StringBuffer('AnneeScolaire(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('libelle: $libelle, ')
          ..write('dateDebut: $dateDebut, ')
          ..write('dateFin: $dateFin, ')
          ..write('isActive: $isActive')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    libelle,
    dateDebut,
    dateFin,
    isActive,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is AnneeScolaire &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.libelle == this.libelle &&
          other.dateDebut == this.dateDebut &&
          other.dateFin == this.dateFin &&
          other.isActive == this.isActive);
}

class AnneeScolairesCompanion extends UpdateCompanion<AnneeScolaire> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<String> libelle;
  final Value<String?> dateDebut;
  final Value<String?> dateFin;
  final Value<bool> isActive;
  const AnneeScolairesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.libelle = const Value.absent(),
    this.dateDebut = const Value.absent(),
    this.dateFin = const Value.absent(),
    this.isActive = const Value.absent(),
  });
  AnneeScolairesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    required String libelle,
    this.dateDebut = const Value.absent(),
    this.dateFin = const Value.absent(),
    this.isActive = const Value.absent(),
  }) : schoolId = Value(schoolId),
       libelle = Value(libelle);
  static Insertable<AnneeScolaire> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<String>? libelle,
    Expression<String>? dateDebut,
    Expression<String>? dateFin,
    Expression<bool>? isActive,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (libelle != null) 'libelle': libelle,
      if (dateDebut != null) 'date_debut': dateDebut,
      if (dateFin != null) 'date_fin': dateFin,
      if (isActive != null) 'is_active': isActive,
    });
  }

  AnneeScolairesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<String>? libelle,
    Value<String?>? dateDebut,
    Value<String?>? dateFin,
    Value<bool>? isActive,
  }) {
    return AnneeScolairesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      libelle: libelle ?? this.libelle,
      dateDebut: dateDebut ?? this.dateDebut,
      dateFin: dateFin ?? this.dateFin,
      isActive: isActive ?? this.isActive,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (libelle.present) {
      map['libelle'] = Variable<String>(libelle.value);
    }
    if (dateDebut.present) {
      map['date_debut'] = Variable<String>(dateDebut.value);
    }
    if (dateFin.present) {
      map['date_fin'] = Variable<String>(dateFin.value);
    }
    if (isActive.present) {
      map['is_active'] = Variable<bool>(isActive.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('AnneeScolairesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('libelle: $libelle, ')
          ..write('dateDebut: $dateDebut, ')
          ..write('dateFin: $dateFin, ')
          ..write('isActive: $isActive')
          ..write(')'))
        .toString();
  }
}

class $TrimestresTable extends Trimestres
    with TableInfo<$TrimestresTable, Trimestre> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $TrimestresTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _anneeScolaireIdMeta = const VerificationMeta(
    'anneeScolaireId',
  );
  @override
  late final GeneratedColumn<int> anneeScolaireId = GeneratedColumn<int>(
    'annee_scolaire_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _libelleMeta = const VerificationMeta(
    'libelle',
  );
  @override
  late final GeneratedColumn<String> libelle = GeneratedColumn<String>(
    'libelle',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _ordreMeta = const VerificationMeta('ordre');
  @override
  late final GeneratedColumn<int> ordre = GeneratedColumn<int>(
    'ordre',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _dateDebutMeta = const VerificationMeta(
    'dateDebut',
  );
  @override
  late final GeneratedColumn<String> dateDebut = GeneratedColumn<String>(
    'date_debut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dateFinMeta = const VerificationMeta(
    'dateFin',
  );
  @override
  late final GeneratedColumn<String> dateFin = GeneratedColumn<String>(
    'date_fin',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _isActiveMeta = const VerificationMeta(
    'isActive',
  );
  @override
  late final GeneratedColumn<bool> isActive = GeneratedColumn<bool>(
    'is_active',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("is_active" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    anneeScolaireId,
    libelle,
    ordre,
    dateDebut,
    dateFin,
    isActive,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'trimestres';
  @override
  VerificationContext validateIntegrity(
    Insertable<Trimestre> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('annee_scolaire_id')) {
      context.handle(
        _anneeScolaireIdMeta,
        anneeScolaireId.isAcceptableOrUnknown(
          data['annee_scolaire_id']!,
          _anneeScolaireIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_anneeScolaireIdMeta);
    }
    if (data.containsKey('libelle')) {
      context.handle(
        _libelleMeta,
        libelle.isAcceptableOrUnknown(data['libelle']!, _libelleMeta),
      );
    } else if (isInserting) {
      context.missing(_libelleMeta);
    }
    if (data.containsKey('ordre')) {
      context.handle(
        _ordreMeta,
        ordre.isAcceptableOrUnknown(data['ordre']!, _ordreMeta),
      );
    }
    if (data.containsKey('date_debut')) {
      context.handle(
        _dateDebutMeta,
        dateDebut.isAcceptableOrUnknown(data['date_debut']!, _dateDebutMeta),
      );
    }
    if (data.containsKey('date_fin')) {
      context.handle(
        _dateFinMeta,
        dateFin.isAcceptableOrUnknown(data['date_fin']!, _dateFinMeta),
      );
    }
    if (data.containsKey('is_active')) {
      context.handle(
        _isActiveMeta,
        isActive.isAcceptableOrUnknown(data['is_active']!, _isActiveMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Trimestre map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Trimestre(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      anneeScolaireId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}annee_scolaire_id'],
      )!,
      libelle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}libelle'],
      )!,
      ordre: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ordre'],
      )!,
      dateDebut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_debut'],
      ),
      dateFin: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_fin'],
      ),
      isActive: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}is_active'],
      )!,
    );
  }

  @override
  $TrimestresTable createAlias(String alias) {
    return $TrimestresTable(attachedDatabase, alias);
  }
}

class Trimestre extends DataClass implements Insertable<Trimestre> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int anneeScolaireId;
  final String libelle;
  final int ordre;
  final String? dateDebut;
  final String? dateFin;
  final bool isActive;
  const Trimestre({
    required this.id,
    required this.etatSync,
    required this.anneeScolaireId,
    required this.libelle,
    required this.ordre,
    this.dateDebut,
    this.dateFin,
    required this.isActive,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['annee_scolaire_id'] = Variable<int>(anneeScolaireId);
    map['libelle'] = Variable<String>(libelle);
    map['ordre'] = Variable<int>(ordre);
    if (!nullToAbsent || dateDebut != null) {
      map['date_debut'] = Variable<String>(dateDebut);
    }
    if (!nullToAbsent || dateFin != null) {
      map['date_fin'] = Variable<String>(dateFin);
    }
    map['is_active'] = Variable<bool>(isActive);
    return map;
  }

  TrimestresCompanion toCompanion(bool nullToAbsent) {
    return TrimestresCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      anneeScolaireId: Value(anneeScolaireId),
      libelle: Value(libelle),
      ordre: Value(ordre),
      dateDebut: dateDebut == null && nullToAbsent
          ? const Value.absent()
          : Value(dateDebut),
      dateFin: dateFin == null && nullToAbsent
          ? const Value.absent()
          : Value(dateFin),
      isActive: Value(isActive),
    );
  }

  factory Trimestre.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Trimestre(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      anneeScolaireId: serializer.fromJson<int>(json['anneeScolaireId']),
      libelle: serializer.fromJson<String>(json['libelle']),
      ordre: serializer.fromJson<int>(json['ordre']),
      dateDebut: serializer.fromJson<String?>(json['dateDebut']),
      dateFin: serializer.fromJson<String?>(json['dateFin']),
      isActive: serializer.fromJson<bool>(json['isActive']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'anneeScolaireId': serializer.toJson<int>(anneeScolaireId),
      'libelle': serializer.toJson<String>(libelle),
      'ordre': serializer.toJson<int>(ordre),
      'dateDebut': serializer.toJson<String?>(dateDebut),
      'dateFin': serializer.toJson<String?>(dateFin),
      'isActive': serializer.toJson<bool>(isActive),
    };
  }

  Trimestre copyWith({
    int? id,
    String? etatSync,
    int? anneeScolaireId,
    String? libelle,
    int? ordre,
    Value<String?> dateDebut = const Value.absent(),
    Value<String?> dateFin = const Value.absent(),
    bool? isActive,
  }) => Trimestre(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    anneeScolaireId: anneeScolaireId ?? this.anneeScolaireId,
    libelle: libelle ?? this.libelle,
    ordre: ordre ?? this.ordre,
    dateDebut: dateDebut.present ? dateDebut.value : this.dateDebut,
    dateFin: dateFin.present ? dateFin.value : this.dateFin,
    isActive: isActive ?? this.isActive,
  );
  Trimestre copyWithCompanion(TrimestresCompanion data) {
    return Trimestre(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      anneeScolaireId: data.anneeScolaireId.present
          ? data.anneeScolaireId.value
          : this.anneeScolaireId,
      libelle: data.libelle.present ? data.libelle.value : this.libelle,
      ordre: data.ordre.present ? data.ordre.value : this.ordre,
      dateDebut: data.dateDebut.present ? data.dateDebut.value : this.dateDebut,
      dateFin: data.dateFin.present ? data.dateFin.value : this.dateFin,
      isActive: data.isActive.present ? data.isActive.value : this.isActive,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Trimestre(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('anneeScolaireId: $anneeScolaireId, ')
          ..write('libelle: $libelle, ')
          ..write('ordre: $ordre, ')
          ..write('dateDebut: $dateDebut, ')
          ..write('dateFin: $dateFin, ')
          ..write('isActive: $isActive')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    anneeScolaireId,
    libelle,
    ordre,
    dateDebut,
    dateFin,
    isActive,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Trimestre &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.anneeScolaireId == this.anneeScolaireId &&
          other.libelle == this.libelle &&
          other.ordre == this.ordre &&
          other.dateDebut == this.dateDebut &&
          other.dateFin == this.dateFin &&
          other.isActive == this.isActive);
}

class TrimestresCompanion extends UpdateCompanion<Trimestre> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> anneeScolaireId;
  final Value<String> libelle;
  final Value<int> ordre;
  final Value<String?> dateDebut;
  final Value<String?> dateFin;
  final Value<bool> isActive;
  const TrimestresCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.anneeScolaireId = const Value.absent(),
    this.libelle = const Value.absent(),
    this.ordre = const Value.absent(),
    this.dateDebut = const Value.absent(),
    this.dateFin = const Value.absent(),
    this.isActive = const Value.absent(),
  });
  TrimestresCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int anneeScolaireId,
    required String libelle,
    this.ordre = const Value.absent(),
    this.dateDebut = const Value.absent(),
    this.dateFin = const Value.absent(),
    this.isActive = const Value.absent(),
  }) : anneeScolaireId = Value(anneeScolaireId),
       libelle = Value(libelle);
  static Insertable<Trimestre> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? anneeScolaireId,
    Expression<String>? libelle,
    Expression<int>? ordre,
    Expression<String>? dateDebut,
    Expression<String>? dateFin,
    Expression<bool>? isActive,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (anneeScolaireId != null) 'annee_scolaire_id': anneeScolaireId,
      if (libelle != null) 'libelle': libelle,
      if (ordre != null) 'ordre': ordre,
      if (dateDebut != null) 'date_debut': dateDebut,
      if (dateFin != null) 'date_fin': dateFin,
      if (isActive != null) 'is_active': isActive,
    });
  }

  TrimestresCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? anneeScolaireId,
    Value<String>? libelle,
    Value<int>? ordre,
    Value<String?>? dateDebut,
    Value<String?>? dateFin,
    Value<bool>? isActive,
  }) {
    return TrimestresCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      anneeScolaireId: anneeScolaireId ?? this.anneeScolaireId,
      libelle: libelle ?? this.libelle,
      ordre: ordre ?? this.ordre,
      dateDebut: dateDebut ?? this.dateDebut,
      dateFin: dateFin ?? this.dateFin,
      isActive: isActive ?? this.isActive,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (anneeScolaireId.present) {
      map['annee_scolaire_id'] = Variable<int>(anneeScolaireId.value);
    }
    if (libelle.present) {
      map['libelle'] = Variable<String>(libelle.value);
    }
    if (ordre.present) {
      map['ordre'] = Variable<int>(ordre.value);
    }
    if (dateDebut.present) {
      map['date_debut'] = Variable<String>(dateDebut.value);
    }
    if (dateFin.present) {
      map['date_fin'] = Variable<String>(dateFin.value);
    }
    if (isActive.present) {
      map['is_active'] = Variable<bool>(isActive.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('TrimestresCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('anneeScolaireId: $anneeScolaireId, ')
          ..write('libelle: $libelle, ')
          ..write('ordre: $ordre, ')
          ..write('dateDebut: $dateDebut, ')
          ..write('dateFin: $dateFin, ')
          ..write('isActive: $isActive')
          ..write(')'))
        .toString();
  }
}

class $SequencesTable extends Sequences
    with TableInfo<$SequencesTable, Sequence> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SequencesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _trimestreIdMeta = const VerificationMeta(
    'trimestreId',
  );
  @override
  late final GeneratedColumn<int> trimestreId = GeneratedColumn<int>(
    'trimestre_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _ordreMeta = const VerificationMeta('ordre');
  @override
  late final GeneratedColumn<int> ordre = GeneratedColumn<int>(
    'ordre',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _libelleMeta = const VerificationMeta(
    'libelle',
  );
  @override
  late final GeneratedColumn<String> libelle = GeneratedColumn<String>(
    'libelle',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    trimestreId,
    ordre,
    libelle,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'sequences';
  @override
  VerificationContext validateIntegrity(
    Insertable<Sequence> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('trimestre_id')) {
      context.handle(
        _trimestreIdMeta,
        trimestreId.isAcceptableOrUnknown(
          data['trimestre_id']!,
          _trimestreIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_trimestreIdMeta);
    }
    if (data.containsKey('ordre')) {
      context.handle(
        _ordreMeta,
        ordre.isAcceptableOrUnknown(data['ordre']!, _ordreMeta),
      );
    }
    if (data.containsKey('libelle')) {
      context.handle(
        _libelleMeta,
        libelle.isAcceptableOrUnknown(data['libelle']!, _libelleMeta),
      );
    } else if (isInserting) {
      context.missing(_libelleMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Sequence map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Sequence(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      trimestreId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}trimestre_id'],
      )!,
      ordre: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ordre'],
      )!,
      libelle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}libelle'],
      )!,
    );
  }

  @override
  $SequencesTable createAlias(String alias) {
    return $SequencesTable(attachedDatabase, alias);
  }
}

class Sequence extends DataClass implements Insertable<Sequence> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int trimestreId;
  final int ordre;
  final String libelle;
  const Sequence({
    required this.id,
    required this.etatSync,
    required this.trimestreId,
    required this.ordre,
    required this.libelle,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['trimestre_id'] = Variable<int>(trimestreId);
    map['ordre'] = Variable<int>(ordre);
    map['libelle'] = Variable<String>(libelle);
    return map;
  }

  SequencesCompanion toCompanion(bool nullToAbsent) {
    return SequencesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      trimestreId: Value(trimestreId),
      ordre: Value(ordre),
      libelle: Value(libelle),
    );
  }

  factory Sequence.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Sequence(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      trimestreId: serializer.fromJson<int>(json['trimestreId']),
      ordre: serializer.fromJson<int>(json['ordre']),
      libelle: serializer.fromJson<String>(json['libelle']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'trimestreId': serializer.toJson<int>(trimestreId),
      'ordre': serializer.toJson<int>(ordre),
      'libelle': serializer.toJson<String>(libelle),
    };
  }

  Sequence copyWith({
    int? id,
    String? etatSync,
    int? trimestreId,
    int? ordre,
    String? libelle,
  }) => Sequence(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    trimestreId: trimestreId ?? this.trimestreId,
    ordre: ordre ?? this.ordre,
    libelle: libelle ?? this.libelle,
  );
  Sequence copyWithCompanion(SequencesCompanion data) {
    return Sequence(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      trimestreId: data.trimestreId.present
          ? data.trimestreId.value
          : this.trimestreId,
      ordre: data.ordre.present ? data.ordre.value : this.ordre,
      libelle: data.libelle.present ? data.libelle.value : this.libelle,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Sequence(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('trimestreId: $trimestreId, ')
          ..write('ordre: $ordre, ')
          ..write('libelle: $libelle')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, etatSync, trimestreId, ordre, libelle);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Sequence &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.trimestreId == this.trimestreId &&
          other.ordre == this.ordre &&
          other.libelle == this.libelle);
}

class SequencesCompanion extends UpdateCompanion<Sequence> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> trimestreId;
  final Value<int> ordre;
  final Value<String> libelle;
  const SequencesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.trimestreId = const Value.absent(),
    this.ordre = const Value.absent(),
    this.libelle = const Value.absent(),
  });
  SequencesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int trimestreId,
    this.ordre = const Value.absent(),
    required String libelle,
  }) : trimestreId = Value(trimestreId),
       libelle = Value(libelle);
  static Insertable<Sequence> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? trimestreId,
    Expression<int>? ordre,
    Expression<String>? libelle,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (trimestreId != null) 'trimestre_id': trimestreId,
      if (ordre != null) 'ordre': ordre,
      if (libelle != null) 'libelle': libelle,
    });
  }

  SequencesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? trimestreId,
    Value<int>? ordre,
    Value<String>? libelle,
  }) {
    return SequencesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      trimestreId: trimestreId ?? this.trimestreId,
      ordre: ordre ?? this.ordre,
      libelle: libelle ?? this.libelle,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (trimestreId.present) {
      map['trimestre_id'] = Variable<int>(trimestreId.value);
    }
    if (ordre.present) {
      map['ordre'] = Variable<int>(ordre.value);
    }
    if (libelle.present) {
      map['libelle'] = Variable<String>(libelle.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SequencesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('trimestreId: $trimestreId, ')
          ..write('ordre: $ordre, ')
          ..write('libelle: $libelle')
          ..write(')'))
        .toString();
  }
}

class $NiveauxTable extends Niveaux with TableInfo<$NiveauxTable, NiveauxData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $NiveauxTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _codeMeta = const VerificationMeta('code');
  @override
  late final GeneratedColumn<String> code = GeneratedColumn<String>(
    'code',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _nameFrMeta = const VerificationMeta('nameFr');
  @override
  late final GeneratedColumn<String> nameFr = GeneratedColumn<String>(
    'name_fr',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _nameEnMeta = const VerificationMeta('nameEn');
  @override
  late final GeneratedColumn<String> nameEn = GeneratedColumn<String>(
    'name_en',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _sousSystemIdMeta = const VerificationMeta(
    'sousSystemId',
  );
  @override
  late final GeneratedColumn<int> sousSystemId = GeneratedColumn<int>(
    'sous_system_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _ordreMeta = const VerificationMeta('ordre');
  @override
  late final GeneratedColumn<int> ordre = GeneratedColumn<int>(
    'ordre',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    code,
    nameFr,
    nameEn,
    sousSystemId,
    schoolId,
    ordre,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'niveaux';
  @override
  VerificationContext validateIntegrity(
    Insertable<NiveauxData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('code')) {
      context.handle(
        _codeMeta,
        code.isAcceptableOrUnknown(data['code']!, _codeMeta),
      );
    }
    if (data.containsKey('name_fr')) {
      context.handle(
        _nameFrMeta,
        nameFr.isAcceptableOrUnknown(data['name_fr']!, _nameFrMeta),
      );
    }
    if (data.containsKey('name_en')) {
      context.handle(
        _nameEnMeta,
        nameEn.isAcceptableOrUnknown(data['name_en']!, _nameEnMeta),
      );
    }
    if (data.containsKey('sous_system_id')) {
      context.handle(
        _sousSystemIdMeta,
        sousSystemId.isAcceptableOrUnknown(
          data['sous_system_id']!,
          _sousSystemIdMeta,
        ),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    }
    if (data.containsKey('ordre')) {
      context.handle(
        _ordreMeta,
        ordre.isAcceptableOrUnknown(data['ordre']!, _ordreMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  NiveauxData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return NiveauxData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      code: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}code'],
      ),
      nameFr: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}name_fr'],
      ),
      nameEn: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}name_en'],
      ),
      sousSystemId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}sous_system_id'],
      ),
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      ),
      ordre: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ordre'],
      )!,
    );
  }

  @override
  $NiveauxTable createAlias(String alias) {
    return $NiveauxTable(attachedDatabase, alias);
  }
}

class NiveauxData extends DataClass implements Insertable<NiveauxData> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final String? code;
  final String? nameFr;
  final String? nameEn;
  final int? sousSystemId;
  final int? schoolId;
  final int ordre;
  const NiveauxData({
    required this.id,
    required this.etatSync,
    this.code,
    this.nameFr,
    this.nameEn,
    this.sousSystemId,
    this.schoolId,
    required this.ordre,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    if (!nullToAbsent || code != null) {
      map['code'] = Variable<String>(code);
    }
    if (!nullToAbsent || nameFr != null) {
      map['name_fr'] = Variable<String>(nameFr);
    }
    if (!nullToAbsent || nameEn != null) {
      map['name_en'] = Variable<String>(nameEn);
    }
    if (!nullToAbsent || sousSystemId != null) {
      map['sous_system_id'] = Variable<int>(sousSystemId);
    }
    if (!nullToAbsent || schoolId != null) {
      map['school_id'] = Variable<int>(schoolId);
    }
    map['ordre'] = Variable<int>(ordre);
    return map;
  }

  NiveauxCompanion toCompanion(bool nullToAbsent) {
    return NiveauxCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      code: code == null && nullToAbsent ? const Value.absent() : Value(code),
      nameFr: nameFr == null && nullToAbsent
          ? const Value.absent()
          : Value(nameFr),
      nameEn: nameEn == null && nullToAbsent
          ? const Value.absent()
          : Value(nameEn),
      sousSystemId: sousSystemId == null && nullToAbsent
          ? const Value.absent()
          : Value(sousSystemId),
      schoolId: schoolId == null && nullToAbsent
          ? const Value.absent()
          : Value(schoolId),
      ordre: Value(ordre),
    );
  }

  factory NiveauxData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return NiveauxData(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      code: serializer.fromJson<String?>(json['code']),
      nameFr: serializer.fromJson<String?>(json['nameFr']),
      nameEn: serializer.fromJson<String?>(json['nameEn']),
      sousSystemId: serializer.fromJson<int?>(json['sousSystemId']),
      schoolId: serializer.fromJson<int?>(json['schoolId']),
      ordre: serializer.fromJson<int>(json['ordre']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'code': serializer.toJson<String?>(code),
      'nameFr': serializer.toJson<String?>(nameFr),
      'nameEn': serializer.toJson<String?>(nameEn),
      'sousSystemId': serializer.toJson<int?>(sousSystemId),
      'schoolId': serializer.toJson<int?>(schoolId),
      'ordre': serializer.toJson<int>(ordre),
    };
  }

  NiveauxData copyWith({
    int? id,
    String? etatSync,
    Value<String?> code = const Value.absent(),
    Value<String?> nameFr = const Value.absent(),
    Value<String?> nameEn = const Value.absent(),
    Value<int?> sousSystemId = const Value.absent(),
    Value<int?> schoolId = const Value.absent(),
    int? ordre,
  }) => NiveauxData(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    code: code.present ? code.value : this.code,
    nameFr: nameFr.present ? nameFr.value : this.nameFr,
    nameEn: nameEn.present ? nameEn.value : this.nameEn,
    sousSystemId: sousSystemId.present ? sousSystemId.value : this.sousSystemId,
    schoolId: schoolId.present ? schoolId.value : this.schoolId,
    ordre: ordre ?? this.ordre,
  );
  NiveauxData copyWithCompanion(NiveauxCompanion data) {
    return NiveauxData(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      code: data.code.present ? data.code.value : this.code,
      nameFr: data.nameFr.present ? data.nameFr.value : this.nameFr,
      nameEn: data.nameEn.present ? data.nameEn.value : this.nameEn,
      sousSystemId: data.sousSystemId.present
          ? data.sousSystemId.value
          : this.sousSystemId,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      ordre: data.ordre.present ? data.ordre.value : this.ordre,
    );
  }

  @override
  String toString() {
    return (StringBuffer('NiveauxData(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('code: $code, ')
          ..write('nameFr: $nameFr, ')
          ..write('nameEn: $nameEn, ')
          ..write('sousSystemId: $sousSystemId, ')
          ..write('schoolId: $schoolId, ')
          ..write('ordre: $ordre')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    code,
    nameFr,
    nameEn,
    sousSystemId,
    schoolId,
    ordre,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is NiveauxData &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.code == this.code &&
          other.nameFr == this.nameFr &&
          other.nameEn == this.nameEn &&
          other.sousSystemId == this.sousSystemId &&
          other.schoolId == this.schoolId &&
          other.ordre == this.ordre);
}

class NiveauxCompanion extends UpdateCompanion<NiveauxData> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<String?> code;
  final Value<String?> nameFr;
  final Value<String?> nameEn;
  final Value<int?> sousSystemId;
  final Value<int?> schoolId;
  final Value<int> ordre;
  const NiveauxCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.code = const Value.absent(),
    this.nameFr = const Value.absent(),
    this.nameEn = const Value.absent(),
    this.sousSystemId = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.ordre = const Value.absent(),
  });
  NiveauxCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.code = const Value.absent(),
    this.nameFr = const Value.absent(),
    this.nameEn = const Value.absent(),
    this.sousSystemId = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.ordre = const Value.absent(),
  });
  static Insertable<NiveauxData> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<String>? code,
    Expression<String>? nameFr,
    Expression<String>? nameEn,
    Expression<int>? sousSystemId,
    Expression<int>? schoolId,
    Expression<int>? ordre,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (code != null) 'code': code,
      if (nameFr != null) 'name_fr': nameFr,
      if (nameEn != null) 'name_en': nameEn,
      if (sousSystemId != null) 'sous_system_id': sousSystemId,
      if (schoolId != null) 'school_id': schoolId,
      if (ordre != null) 'ordre': ordre,
    });
  }

  NiveauxCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<String?>? code,
    Value<String?>? nameFr,
    Value<String?>? nameEn,
    Value<int?>? sousSystemId,
    Value<int?>? schoolId,
    Value<int>? ordre,
  }) {
    return NiveauxCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      code: code ?? this.code,
      nameFr: nameFr ?? this.nameFr,
      nameEn: nameEn ?? this.nameEn,
      sousSystemId: sousSystemId ?? this.sousSystemId,
      schoolId: schoolId ?? this.schoolId,
      ordre: ordre ?? this.ordre,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (code.present) {
      map['code'] = Variable<String>(code.value);
    }
    if (nameFr.present) {
      map['name_fr'] = Variable<String>(nameFr.value);
    }
    if (nameEn.present) {
      map['name_en'] = Variable<String>(nameEn.value);
    }
    if (sousSystemId.present) {
      map['sous_system_id'] = Variable<int>(sousSystemId.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (ordre.present) {
      map['ordre'] = Variable<int>(ordre.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('NiveauxCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('code: $code, ')
          ..write('nameFr: $nameFr, ')
          ..write('nameEn: $nameEn, ')
          ..write('sousSystemId: $sousSystemId, ')
          ..write('schoolId: $schoolId, ')
          ..write('ordre: $ordre')
          ..write(')'))
        .toString();
  }
}

class $MatieresTable extends Matieres with TableInfo<$MatieresTable, Matiere> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $MatieresTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _departementIdMeta = const VerificationMeta(
    'departementId',
  );
  @override
  late final GeneratedColumn<int> departementId = GeneratedColumn<int>(
    'departement_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _nomMeta = const VerificationMeta('nom');
  @override
  late final GeneratedColumn<String> nom = GeneratedColumn<String>(
    'nom',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _nomEnMeta = const VerificationMeta('nomEn');
  @override
  late final GeneratedColumn<String> nomEn = GeneratedColumn<String>(
    'nom_en',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _abbreviationMeta = const VerificationMeta(
    'abbreviation',
  );
  @override
  late final GeneratedColumn<String> abbreviation = GeneratedColumn<String>(
    'abbreviation',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _notationMeta = const VerificationMeta(
    'notation',
  );
  @override
  late final GeneratedColumn<int> notation = GeneratedColumn<int>(
    'notation',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _evaluePratiqueMeta = const VerificationMeta(
    'evaluePratique',
  );
  @override
  late final GeneratedColumn<bool> evaluePratique = GeneratedColumn<bool>(
    'evalue_pratique',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("evalue_pratique" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _repartitionVoletsMeta = const VerificationMeta(
    'repartitionVolets',
  );
  @override
  late final GeneratedColumn<String> repartitionVolets =
      GeneratedColumn<String>(
        'repartition_volets',
        aliasedName,
        true,
        type: DriftSqlType.string,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _statutMeta = const VerificationMeta('statut');
  @override
  late final GeneratedColumn<String> statut = GeneratedColumn<String>(
    'statut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    departementId,
    nom,
    nomEn,
    abbreviation,
    notation,
    evaluePratique,
    repartitionVolets,
    statut,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'matieres';
  @override
  VerificationContext validateIntegrity(
    Insertable<Matiere> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('departement_id')) {
      context.handle(
        _departementIdMeta,
        departementId.isAcceptableOrUnknown(
          data['departement_id']!,
          _departementIdMeta,
        ),
      );
    }
    if (data.containsKey('nom')) {
      context.handle(
        _nomMeta,
        nom.isAcceptableOrUnknown(data['nom']!, _nomMeta),
      );
    } else if (isInserting) {
      context.missing(_nomMeta);
    }
    if (data.containsKey('nom_en')) {
      context.handle(
        _nomEnMeta,
        nomEn.isAcceptableOrUnknown(data['nom_en']!, _nomEnMeta),
      );
    }
    if (data.containsKey('abbreviation')) {
      context.handle(
        _abbreviationMeta,
        abbreviation.isAcceptableOrUnknown(
          data['abbreviation']!,
          _abbreviationMeta,
        ),
      );
    }
    if (data.containsKey('notation')) {
      context.handle(
        _notationMeta,
        notation.isAcceptableOrUnknown(data['notation']!, _notationMeta),
      );
    }
    if (data.containsKey('evalue_pratique')) {
      context.handle(
        _evaluePratiqueMeta,
        evaluePratique.isAcceptableOrUnknown(
          data['evalue_pratique']!,
          _evaluePratiqueMeta,
        ),
      );
    }
    if (data.containsKey('repartition_volets')) {
      context.handle(
        _repartitionVoletsMeta,
        repartitionVolets.isAcceptableOrUnknown(
          data['repartition_volets']!,
          _repartitionVoletsMeta,
        ),
      );
    }
    if (data.containsKey('statut')) {
      context.handle(
        _statutMeta,
        statut.isAcceptableOrUnknown(data['statut']!, _statutMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Matiere map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Matiere(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      departementId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}departement_id'],
      ),
      nom: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nom'],
      )!,
      nomEn: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nom_en'],
      ),
      abbreviation: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}abbreviation'],
      ),
      notation: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}notation'],
      ),
      evaluePratique: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}evalue_pratique'],
      )!,
      repartitionVolets: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}repartition_volets'],
      ),
      statut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}statut'],
      ),
    );
  }

  @override
  $MatieresTable createAlias(String alias) {
    return $MatieresTable(attachedDatabase, alias);
  }
}

class Matiere extends DataClass implements Insertable<Matiere> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final int? departementId;
  final String nom;
  final String? nomEn;
  final String? abbreviation;
  final int? notation;
  final bool evaluePratique;
  final String? repartitionVolets;
  final String? statut;
  const Matiere({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    this.departementId,
    required this.nom,
    this.nomEn,
    this.abbreviation,
    this.notation,
    required this.evaluePratique,
    this.repartitionVolets,
    this.statut,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    if (!nullToAbsent || departementId != null) {
      map['departement_id'] = Variable<int>(departementId);
    }
    map['nom'] = Variable<String>(nom);
    if (!nullToAbsent || nomEn != null) {
      map['nom_en'] = Variable<String>(nomEn);
    }
    if (!nullToAbsent || abbreviation != null) {
      map['abbreviation'] = Variable<String>(abbreviation);
    }
    if (!nullToAbsent || notation != null) {
      map['notation'] = Variable<int>(notation);
    }
    map['evalue_pratique'] = Variable<bool>(evaluePratique);
    if (!nullToAbsent || repartitionVolets != null) {
      map['repartition_volets'] = Variable<String>(repartitionVolets);
    }
    if (!nullToAbsent || statut != null) {
      map['statut'] = Variable<String>(statut);
    }
    return map;
  }

  MatieresCompanion toCompanion(bool nullToAbsent) {
    return MatieresCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      departementId: departementId == null && nullToAbsent
          ? const Value.absent()
          : Value(departementId),
      nom: Value(nom),
      nomEn: nomEn == null && nullToAbsent
          ? const Value.absent()
          : Value(nomEn),
      abbreviation: abbreviation == null && nullToAbsent
          ? const Value.absent()
          : Value(abbreviation),
      notation: notation == null && nullToAbsent
          ? const Value.absent()
          : Value(notation),
      evaluePratique: Value(evaluePratique),
      repartitionVolets: repartitionVolets == null && nullToAbsent
          ? const Value.absent()
          : Value(repartitionVolets),
      statut: statut == null && nullToAbsent
          ? const Value.absent()
          : Value(statut),
    );
  }

  factory Matiere.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Matiere(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      departementId: serializer.fromJson<int?>(json['departementId']),
      nom: serializer.fromJson<String>(json['nom']),
      nomEn: serializer.fromJson<String?>(json['nomEn']),
      abbreviation: serializer.fromJson<String?>(json['abbreviation']),
      notation: serializer.fromJson<int?>(json['notation']),
      evaluePratique: serializer.fromJson<bool>(json['evaluePratique']),
      repartitionVolets: serializer.fromJson<String?>(
        json['repartitionVolets'],
      ),
      statut: serializer.fromJson<String?>(json['statut']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'departementId': serializer.toJson<int?>(departementId),
      'nom': serializer.toJson<String>(nom),
      'nomEn': serializer.toJson<String?>(nomEn),
      'abbreviation': serializer.toJson<String?>(abbreviation),
      'notation': serializer.toJson<int?>(notation),
      'evaluePratique': serializer.toJson<bool>(evaluePratique),
      'repartitionVolets': serializer.toJson<String?>(repartitionVolets),
      'statut': serializer.toJson<String?>(statut),
    };
  }

  Matiere copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    Value<int?> departementId = const Value.absent(),
    String? nom,
    Value<String?> nomEn = const Value.absent(),
    Value<String?> abbreviation = const Value.absent(),
    Value<int?> notation = const Value.absent(),
    bool? evaluePratique,
    Value<String?> repartitionVolets = const Value.absent(),
    Value<String?> statut = const Value.absent(),
  }) => Matiere(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    departementId: departementId.present
        ? departementId.value
        : this.departementId,
    nom: nom ?? this.nom,
    nomEn: nomEn.present ? nomEn.value : this.nomEn,
    abbreviation: abbreviation.present ? abbreviation.value : this.abbreviation,
    notation: notation.present ? notation.value : this.notation,
    evaluePratique: evaluePratique ?? this.evaluePratique,
    repartitionVolets: repartitionVolets.present
        ? repartitionVolets.value
        : this.repartitionVolets,
    statut: statut.present ? statut.value : this.statut,
  );
  Matiere copyWithCompanion(MatieresCompanion data) {
    return Matiere(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      departementId: data.departementId.present
          ? data.departementId.value
          : this.departementId,
      nom: data.nom.present ? data.nom.value : this.nom,
      nomEn: data.nomEn.present ? data.nomEn.value : this.nomEn,
      abbreviation: data.abbreviation.present
          ? data.abbreviation.value
          : this.abbreviation,
      notation: data.notation.present ? data.notation.value : this.notation,
      evaluePratique: data.evaluePratique.present
          ? data.evaluePratique.value
          : this.evaluePratique,
      repartitionVolets: data.repartitionVolets.present
          ? data.repartitionVolets.value
          : this.repartitionVolets,
      statut: data.statut.present ? data.statut.value : this.statut,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Matiere(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('departementId: $departementId, ')
          ..write('nom: $nom, ')
          ..write('nomEn: $nomEn, ')
          ..write('abbreviation: $abbreviation, ')
          ..write('notation: $notation, ')
          ..write('evaluePratique: $evaluePratique, ')
          ..write('repartitionVolets: $repartitionVolets, ')
          ..write('statut: $statut')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    departementId,
    nom,
    nomEn,
    abbreviation,
    notation,
    evaluePratique,
    repartitionVolets,
    statut,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Matiere &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.departementId == this.departementId &&
          other.nom == this.nom &&
          other.nomEn == this.nomEn &&
          other.abbreviation == this.abbreviation &&
          other.notation == this.notation &&
          other.evaluePratique == this.evaluePratique &&
          other.repartitionVolets == this.repartitionVolets &&
          other.statut == this.statut);
}

class MatieresCompanion extends UpdateCompanion<Matiere> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<int?> departementId;
  final Value<String> nom;
  final Value<String?> nomEn;
  final Value<String?> abbreviation;
  final Value<int?> notation;
  final Value<bool> evaluePratique;
  final Value<String?> repartitionVolets;
  final Value<String?> statut;
  const MatieresCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.departementId = const Value.absent(),
    this.nom = const Value.absent(),
    this.nomEn = const Value.absent(),
    this.abbreviation = const Value.absent(),
    this.notation = const Value.absent(),
    this.evaluePratique = const Value.absent(),
    this.repartitionVolets = const Value.absent(),
    this.statut = const Value.absent(),
  });
  MatieresCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    this.departementId = const Value.absent(),
    required String nom,
    this.nomEn = const Value.absent(),
    this.abbreviation = const Value.absent(),
    this.notation = const Value.absent(),
    this.evaluePratique = const Value.absent(),
    this.repartitionVolets = const Value.absent(),
    this.statut = const Value.absent(),
  }) : schoolId = Value(schoolId),
       nom = Value(nom);
  static Insertable<Matiere> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<int>? departementId,
    Expression<String>? nom,
    Expression<String>? nomEn,
    Expression<String>? abbreviation,
    Expression<int>? notation,
    Expression<bool>? evaluePratique,
    Expression<String>? repartitionVolets,
    Expression<String>? statut,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (departementId != null) 'departement_id': departementId,
      if (nom != null) 'nom': nom,
      if (nomEn != null) 'nom_en': nomEn,
      if (abbreviation != null) 'abbreviation': abbreviation,
      if (notation != null) 'notation': notation,
      if (evaluePratique != null) 'evalue_pratique': evaluePratique,
      if (repartitionVolets != null) 'repartition_volets': repartitionVolets,
      if (statut != null) 'statut': statut,
    });
  }

  MatieresCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<int?>? departementId,
    Value<String>? nom,
    Value<String?>? nomEn,
    Value<String?>? abbreviation,
    Value<int?>? notation,
    Value<bool>? evaluePratique,
    Value<String?>? repartitionVolets,
    Value<String?>? statut,
  }) {
    return MatieresCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      departementId: departementId ?? this.departementId,
      nom: nom ?? this.nom,
      nomEn: nomEn ?? this.nomEn,
      abbreviation: abbreviation ?? this.abbreviation,
      notation: notation ?? this.notation,
      evaluePratique: evaluePratique ?? this.evaluePratique,
      repartitionVolets: repartitionVolets ?? this.repartitionVolets,
      statut: statut ?? this.statut,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (departementId.present) {
      map['departement_id'] = Variable<int>(departementId.value);
    }
    if (nom.present) {
      map['nom'] = Variable<String>(nom.value);
    }
    if (nomEn.present) {
      map['nom_en'] = Variable<String>(nomEn.value);
    }
    if (abbreviation.present) {
      map['abbreviation'] = Variable<String>(abbreviation.value);
    }
    if (notation.present) {
      map['notation'] = Variable<int>(notation.value);
    }
    if (evaluePratique.present) {
      map['evalue_pratique'] = Variable<bool>(evaluePratique.value);
    }
    if (repartitionVolets.present) {
      map['repartition_volets'] = Variable<String>(repartitionVolets.value);
    }
    if (statut.present) {
      map['statut'] = Variable<String>(statut.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('MatieresCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('departementId: $departementId, ')
          ..write('nom: $nom, ')
          ..write('nomEn: $nomEn, ')
          ..write('abbreviation: $abbreviation, ')
          ..write('notation: $notation, ')
          ..write('evaluePratique: $evaluePratique, ')
          ..write('repartitionVolets: $repartitionVolets, ')
          ..write('statut: $statut')
          ..write(')'))
        .toString();
  }
}

class $ClassesTable extends Classes with TableInfo<$ClassesTable, ClassesData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ClassesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _niveauIdMeta = const VerificationMeta(
    'niveauId',
  );
  @override
  late final GeneratedColumn<int> niveauId = GeneratedColumn<int>(
    'niveau_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _niveauScolaireIdMeta = const VerificationMeta(
    'niveauScolaireId',
  );
  @override
  late final GeneratedColumn<int> niveauScolaireId = GeneratedColumn<int>(
    'niveau_scolaire_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _anneeScolaireIdMeta = const VerificationMeta(
    'anneeScolaireId',
  );
  @override
  late final GeneratedColumn<int> anneeScolaireId = GeneratedColumn<int>(
    'annee_scolaire_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _professeurPrincipalIdMeta =
      const VerificationMeta('professeurPrincipalId');
  @override
  late final GeneratedColumn<int> professeurPrincipalId = GeneratedColumn<int>(
    'professeur_principal_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _titulaireIdMeta = const VerificationMeta(
    'titulaireId',
  );
  @override
  late final GeneratedColumn<int> titulaireId = GeneratedColumn<int>(
    'titulaire_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _surveillantGeneralIdMeta =
      const VerificationMeta('surveillantGeneralId');
  @override
  late final GeneratedColumn<int> surveillantGeneralId = GeneratedColumn<int>(
    'surveillant_general_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _nomMeta = const VerificationMeta('nom');
  @override
  late final GeneratedColumn<String> nom = GeneratedColumn<String>(
    'nom',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _sigleMeta = const VerificationMeta('sigle');
  @override
  late final GeneratedColumn<String> sigle = GeneratedColumn<String>(
    'sigle',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _sousSystemeIdMeta = const VerificationMeta(
    'sousSystemeId',
  );
  @override
  late final GeneratedColumn<int> sousSystemeId = GeneratedColumn<int>(
    'sous_systeme_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _niveauClasseMeta = const VerificationMeta(
    'niveauClasse',
  );
  @override
  late final GeneratedColumn<String> niveauClasse = GeneratedColumn<String>(
    'niveau_classe',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _filiereMeta = const VerificationMeta(
    'filiere',
  );
  @override
  late final GeneratedColumn<String> filiere = GeneratedColumn<String>(
    'filiere',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _capaciteMeta = const VerificationMeta(
    'capacite',
  );
  @override
  late final GeneratedColumn<int> capacite = GeneratedColumn<int>(
    'capacite',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _qrTokenMeta = const VerificationMeta(
    'qrToken',
  );
  @override
  late final GeneratedColumn<String> qrToken = GeneratedColumn<String>(
    'qr_token',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    niveauId,
    niveauScolaireId,
    anneeScolaireId,
    professeurPrincipalId,
    titulaireId,
    surveillantGeneralId,
    nom,
    sigle,
    sousSystemeId,
    niveauClasse,
    filiere,
    capacite,
    qrToken,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'classes';
  @override
  VerificationContext validateIntegrity(
    Insertable<ClassesData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('niveau_id')) {
      context.handle(
        _niveauIdMeta,
        niveauId.isAcceptableOrUnknown(data['niveau_id']!, _niveauIdMeta),
      );
    }
    if (data.containsKey('niveau_scolaire_id')) {
      context.handle(
        _niveauScolaireIdMeta,
        niveauScolaireId.isAcceptableOrUnknown(
          data['niveau_scolaire_id']!,
          _niveauScolaireIdMeta,
        ),
      );
    }
    if (data.containsKey('annee_scolaire_id')) {
      context.handle(
        _anneeScolaireIdMeta,
        anneeScolaireId.isAcceptableOrUnknown(
          data['annee_scolaire_id']!,
          _anneeScolaireIdMeta,
        ),
      );
    }
    if (data.containsKey('professeur_principal_id')) {
      context.handle(
        _professeurPrincipalIdMeta,
        professeurPrincipalId.isAcceptableOrUnknown(
          data['professeur_principal_id']!,
          _professeurPrincipalIdMeta,
        ),
      );
    }
    if (data.containsKey('titulaire_id')) {
      context.handle(
        _titulaireIdMeta,
        titulaireId.isAcceptableOrUnknown(
          data['titulaire_id']!,
          _titulaireIdMeta,
        ),
      );
    }
    if (data.containsKey('surveillant_general_id')) {
      context.handle(
        _surveillantGeneralIdMeta,
        surveillantGeneralId.isAcceptableOrUnknown(
          data['surveillant_general_id']!,
          _surveillantGeneralIdMeta,
        ),
      );
    }
    if (data.containsKey('nom')) {
      context.handle(
        _nomMeta,
        nom.isAcceptableOrUnknown(data['nom']!, _nomMeta),
      );
    } else if (isInserting) {
      context.missing(_nomMeta);
    }
    if (data.containsKey('sigle')) {
      context.handle(
        _sigleMeta,
        sigle.isAcceptableOrUnknown(data['sigle']!, _sigleMeta),
      );
    }
    if (data.containsKey('sous_systeme_id')) {
      context.handle(
        _sousSystemeIdMeta,
        sousSystemeId.isAcceptableOrUnknown(
          data['sous_systeme_id']!,
          _sousSystemeIdMeta,
        ),
      );
    }
    if (data.containsKey('niveau_classe')) {
      context.handle(
        _niveauClasseMeta,
        niveauClasse.isAcceptableOrUnknown(
          data['niveau_classe']!,
          _niveauClasseMeta,
        ),
      );
    }
    if (data.containsKey('filiere')) {
      context.handle(
        _filiereMeta,
        filiere.isAcceptableOrUnknown(data['filiere']!, _filiereMeta),
      );
    }
    if (data.containsKey('capacite')) {
      context.handle(
        _capaciteMeta,
        capacite.isAcceptableOrUnknown(data['capacite']!, _capaciteMeta),
      );
    }
    if (data.containsKey('qr_token')) {
      context.handle(
        _qrTokenMeta,
        qrToken.isAcceptableOrUnknown(data['qr_token']!, _qrTokenMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  ClassesData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return ClassesData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      niveauId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}niveau_id'],
      ),
      niveauScolaireId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}niveau_scolaire_id'],
      ),
      anneeScolaireId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}annee_scolaire_id'],
      ),
      professeurPrincipalId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}professeur_principal_id'],
      ),
      titulaireId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}titulaire_id'],
      ),
      surveillantGeneralId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}surveillant_general_id'],
      ),
      nom: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nom'],
      )!,
      sigle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}sigle'],
      ),
      sousSystemeId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}sous_systeme_id'],
      ),
      niveauClasse: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}niveau_classe'],
      ),
      filiere: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}filiere'],
      ),
      capacite: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}capacite'],
      ),
      qrToken: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}qr_token'],
      ),
    );
  }

  @override
  $ClassesTable createAlias(String alias) {
    return $ClassesTable(attachedDatabase, alias);
  }
}

class ClassesData extends DataClass implements Insertable<ClassesData> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final int? niveauId;
  final int? niveauScolaireId;
  final int? anneeScolaireId;
  final int? professeurPrincipalId;
  final int? titulaireId;
  final int? surveillantGeneralId;
  final String nom;
  final String? sigle;
  final int? sousSystemeId;
  final String? niveauClasse;
  final String? filiere;
  final int? capacite;
  final String? qrToken;
  const ClassesData({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    this.niveauId,
    this.niveauScolaireId,
    this.anneeScolaireId,
    this.professeurPrincipalId,
    this.titulaireId,
    this.surveillantGeneralId,
    required this.nom,
    this.sigle,
    this.sousSystemeId,
    this.niveauClasse,
    this.filiere,
    this.capacite,
    this.qrToken,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    if (!nullToAbsent || niveauId != null) {
      map['niveau_id'] = Variable<int>(niveauId);
    }
    if (!nullToAbsent || niveauScolaireId != null) {
      map['niveau_scolaire_id'] = Variable<int>(niveauScolaireId);
    }
    if (!nullToAbsent || anneeScolaireId != null) {
      map['annee_scolaire_id'] = Variable<int>(anneeScolaireId);
    }
    if (!nullToAbsent || professeurPrincipalId != null) {
      map['professeur_principal_id'] = Variable<int>(professeurPrincipalId);
    }
    if (!nullToAbsent || titulaireId != null) {
      map['titulaire_id'] = Variable<int>(titulaireId);
    }
    if (!nullToAbsent || surveillantGeneralId != null) {
      map['surveillant_general_id'] = Variable<int>(surveillantGeneralId);
    }
    map['nom'] = Variable<String>(nom);
    if (!nullToAbsent || sigle != null) {
      map['sigle'] = Variable<String>(sigle);
    }
    if (!nullToAbsent || sousSystemeId != null) {
      map['sous_systeme_id'] = Variable<int>(sousSystemeId);
    }
    if (!nullToAbsent || niveauClasse != null) {
      map['niveau_classe'] = Variable<String>(niveauClasse);
    }
    if (!nullToAbsent || filiere != null) {
      map['filiere'] = Variable<String>(filiere);
    }
    if (!nullToAbsent || capacite != null) {
      map['capacite'] = Variable<int>(capacite);
    }
    if (!nullToAbsent || qrToken != null) {
      map['qr_token'] = Variable<String>(qrToken);
    }
    return map;
  }

  ClassesCompanion toCompanion(bool nullToAbsent) {
    return ClassesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      niveauId: niveauId == null && nullToAbsent
          ? const Value.absent()
          : Value(niveauId),
      niveauScolaireId: niveauScolaireId == null && nullToAbsent
          ? const Value.absent()
          : Value(niveauScolaireId),
      anneeScolaireId: anneeScolaireId == null && nullToAbsent
          ? const Value.absent()
          : Value(anneeScolaireId),
      professeurPrincipalId: professeurPrincipalId == null && nullToAbsent
          ? const Value.absent()
          : Value(professeurPrincipalId),
      titulaireId: titulaireId == null && nullToAbsent
          ? const Value.absent()
          : Value(titulaireId),
      surveillantGeneralId: surveillantGeneralId == null && nullToAbsent
          ? const Value.absent()
          : Value(surveillantGeneralId),
      nom: Value(nom),
      sigle: sigle == null && nullToAbsent
          ? const Value.absent()
          : Value(sigle),
      sousSystemeId: sousSystemeId == null && nullToAbsent
          ? const Value.absent()
          : Value(sousSystemeId),
      niveauClasse: niveauClasse == null && nullToAbsent
          ? const Value.absent()
          : Value(niveauClasse),
      filiere: filiere == null && nullToAbsent
          ? const Value.absent()
          : Value(filiere),
      capacite: capacite == null && nullToAbsent
          ? const Value.absent()
          : Value(capacite),
      qrToken: qrToken == null && nullToAbsent
          ? const Value.absent()
          : Value(qrToken),
    );
  }

  factory ClassesData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return ClassesData(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      niveauId: serializer.fromJson<int?>(json['niveauId']),
      niveauScolaireId: serializer.fromJson<int?>(json['niveauScolaireId']),
      anneeScolaireId: serializer.fromJson<int?>(json['anneeScolaireId']),
      professeurPrincipalId: serializer.fromJson<int?>(
        json['professeurPrincipalId'],
      ),
      titulaireId: serializer.fromJson<int?>(json['titulaireId']),
      surveillantGeneralId: serializer.fromJson<int?>(
        json['surveillantGeneralId'],
      ),
      nom: serializer.fromJson<String>(json['nom']),
      sigle: serializer.fromJson<String?>(json['sigle']),
      sousSystemeId: serializer.fromJson<int?>(json['sousSystemeId']),
      niveauClasse: serializer.fromJson<String?>(json['niveauClasse']),
      filiere: serializer.fromJson<String?>(json['filiere']),
      capacite: serializer.fromJson<int?>(json['capacite']),
      qrToken: serializer.fromJson<String?>(json['qrToken']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'niveauId': serializer.toJson<int?>(niveauId),
      'niveauScolaireId': serializer.toJson<int?>(niveauScolaireId),
      'anneeScolaireId': serializer.toJson<int?>(anneeScolaireId),
      'professeurPrincipalId': serializer.toJson<int?>(professeurPrincipalId),
      'titulaireId': serializer.toJson<int?>(titulaireId),
      'surveillantGeneralId': serializer.toJson<int?>(surveillantGeneralId),
      'nom': serializer.toJson<String>(nom),
      'sigle': serializer.toJson<String?>(sigle),
      'sousSystemeId': serializer.toJson<int?>(sousSystemeId),
      'niveauClasse': serializer.toJson<String?>(niveauClasse),
      'filiere': serializer.toJson<String?>(filiere),
      'capacite': serializer.toJson<int?>(capacite),
      'qrToken': serializer.toJson<String?>(qrToken),
    };
  }

  ClassesData copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    Value<int?> niveauId = const Value.absent(),
    Value<int?> niveauScolaireId = const Value.absent(),
    Value<int?> anneeScolaireId = const Value.absent(),
    Value<int?> professeurPrincipalId = const Value.absent(),
    Value<int?> titulaireId = const Value.absent(),
    Value<int?> surveillantGeneralId = const Value.absent(),
    String? nom,
    Value<String?> sigle = const Value.absent(),
    Value<int?> sousSystemeId = const Value.absent(),
    Value<String?> niveauClasse = const Value.absent(),
    Value<String?> filiere = const Value.absent(),
    Value<int?> capacite = const Value.absent(),
    Value<String?> qrToken = const Value.absent(),
  }) => ClassesData(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    niveauId: niveauId.present ? niveauId.value : this.niveauId,
    niveauScolaireId: niveauScolaireId.present
        ? niveauScolaireId.value
        : this.niveauScolaireId,
    anneeScolaireId: anneeScolaireId.present
        ? anneeScolaireId.value
        : this.anneeScolaireId,
    professeurPrincipalId: professeurPrincipalId.present
        ? professeurPrincipalId.value
        : this.professeurPrincipalId,
    titulaireId: titulaireId.present ? titulaireId.value : this.titulaireId,
    surveillantGeneralId: surveillantGeneralId.present
        ? surveillantGeneralId.value
        : this.surveillantGeneralId,
    nom: nom ?? this.nom,
    sigle: sigle.present ? sigle.value : this.sigle,
    sousSystemeId: sousSystemeId.present
        ? sousSystemeId.value
        : this.sousSystemeId,
    niveauClasse: niveauClasse.present ? niveauClasse.value : this.niveauClasse,
    filiere: filiere.present ? filiere.value : this.filiere,
    capacite: capacite.present ? capacite.value : this.capacite,
    qrToken: qrToken.present ? qrToken.value : this.qrToken,
  );
  ClassesData copyWithCompanion(ClassesCompanion data) {
    return ClassesData(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      niveauId: data.niveauId.present ? data.niveauId.value : this.niveauId,
      niveauScolaireId: data.niveauScolaireId.present
          ? data.niveauScolaireId.value
          : this.niveauScolaireId,
      anneeScolaireId: data.anneeScolaireId.present
          ? data.anneeScolaireId.value
          : this.anneeScolaireId,
      professeurPrincipalId: data.professeurPrincipalId.present
          ? data.professeurPrincipalId.value
          : this.professeurPrincipalId,
      titulaireId: data.titulaireId.present
          ? data.titulaireId.value
          : this.titulaireId,
      surveillantGeneralId: data.surveillantGeneralId.present
          ? data.surveillantGeneralId.value
          : this.surveillantGeneralId,
      nom: data.nom.present ? data.nom.value : this.nom,
      sigle: data.sigle.present ? data.sigle.value : this.sigle,
      sousSystemeId: data.sousSystemeId.present
          ? data.sousSystemeId.value
          : this.sousSystemeId,
      niveauClasse: data.niveauClasse.present
          ? data.niveauClasse.value
          : this.niveauClasse,
      filiere: data.filiere.present ? data.filiere.value : this.filiere,
      capacite: data.capacite.present ? data.capacite.value : this.capacite,
      qrToken: data.qrToken.present ? data.qrToken.value : this.qrToken,
    );
  }

  @override
  String toString() {
    return (StringBuffer('ClassesData(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('niveauId: $niveauId, ')
          ..write('niveauScolaireId: $niveauScolaireId, ')
          ..write('anneeScolaireId: $anneeScolaireId, ')
          ..write('professeurPrincipalId: $professeurPrincipalId, ')
          ..write('titulaireId: $titulaireId, ')
          ..write('surveillantGeneralId: $surveillantGeneralId, ')
          ..write('nom: $nom, ')
          ..write('sigle: $sigle, ')
          ..write('sousSystemeId: $sousSystemeId, ')
          ..write('niveauClasse: $niveauClasse, ')
          ..write('filiere: $filiere, ')
          ..write('capacite: $capacite, ')
          ..write('qrToken: $qrToken')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    niveauId,
    niveauScolaireId,
    anneeScolaireId,
    professeurPrincipalId,
    titulaireId,
    surveillantGeneralId,
    nom,
    sigle,
    sousSystemeId,
    niveauClasse,
    filiere,
    capacite,
    qrToken,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ClassesData &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.niveauId == this.niveauId &&
          other.niveauScolaireId == this.niveauScolaireId &&
          other.anneeScolaireId == this.anneeScolaireId &&
          other.professeurPrincipalId == this.professeurPrincipalId &&
          other.titulaireId == this.titulaireId &&
          other.surveillantGeneralId == this.surveillantGeneralId &&
          other.nom == this.nom &&
          other.sigle == this.sigle &&
          other.sousSystemeId == this.sousSystemeId &&
          other.niveauClasse == this.niveauClasse &&
          other.filiere == this.filiere &&
          other.capacite == this.capacite &&
          other.qrToken == this.qrToken);
}

class ClassesCompanion extends UpdateCompanion<ClassesData> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<int?> niveauId;
  final Value<int?> niveauScolaireId;
  final Value<int?> anneeScolaireId;
  final Value<int?> professeurPrincipalId;
  final Value<int?> titulaireId;
  final Value<int?> surveillantGeneralId;
  final Value<String> nom;
  final Value<String?> sigle;
  final Value<int?> sousSystemeId;
  final Value<String?> niveauClasse;
  final Value<String?> filiere;
  final Value<int?> capacite;
  final Value<String?> qrToken;
  const ClassesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.niveauId = const Value.absent(),
    this.niveauScolaireId = const Value.absent(),
    this.anneeScolaireId = const Value.absent(),
    this.professeurPrincipalId = const Value.absent(),
    this.titulaireId = const Value.absent(),
    this.surveillantGeneralId = const Value.absent(),
    this.nom = const Value.absent(),
    this.sigle = const Value.absent(),
    this.sousSystemeId = const Value.absent(),
    this.niveauClasse = const Value.absent(),
    this.filiere = const Value.absent(),
    this.capacite = const Value.absent(),
    this.qrToken = const Value.absent(),
  });
  ClassesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    this.niveauId = const Value.absent(),
    this.niveauScolaireId = const Value.absent(),
    this.anneeScolaireId = const Value.absent(),
    this.professeurPrincipalId = const Value.absent(),
    this.titulaireId = const Value.absent(),
    this.surveillantGeneralId = const Value.absent(),
    required String nom,
    this.sigle = const Value.absent(),
    this.sousSystemeId = const Value.absent(),
    this.niveauClasse = const Value.absent(),
    this.filiere = const Value.absent(),
    this.capacite = const Value.absent(),
    this.qrToken = const Value.absent(),
  }) : schoolId = Value(schoolId),
       nom = Value(nom);
  static Insertable<ClassesData> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<int>? niveauId,
    Expression<int>? niveauScolaireId,
    Expression<int>? anneeScolaireId,
    Expression<int>? professeurPrincipalId,
    Expression<int>? titulaireId,
    Expression<int>? surveillantGeneralId,
    Expression<String>? nom,
    Expression<String>? sigle,
    Expression<int>? sousSystemeId,
    Expression<String>? niveauClasse,
    Expression<String>? filiere,
    Expression<int>? capacite,
    Expression<String>? qrToken,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (niveauId != null) 'niveau_id': niveauId,
      if (niveauScolaireId != null) 'niveau_scolaire_id': niveauScolaireId,
      if (anneeScolaireId != null) 'annee_scolaire_id': anneeScolaireId,
      if (professeurPrincipalId != null)
        'professeur_principal_id': professeurPrincipalId,
      if (titulaireId != null) 'titulaire_id': titulaireId,
      if (surveillantGeneralId != null)
        'surveillant_general_id': surveillantGeneralId,
      if (nom != null) 'nom': nom,
      if (sigle != null) 'sigle': sigle,
      if (sousSystemeId != null) 'sous_systeme_id': sousSystemeId,
      if (niveauClasse != null) 'niveau_classe': niveauClasse,
      if (filiere != null) 'filiere': filiere,
      if (capacite != null) 'capacite': capacite,
      if (qrToken != null) 'qr_token': qrToken,
    });
  }

  ClassesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<int?>? niveauId,
    Value<int?>? niveauScolaireId,
    Value<int?>? anneeScolaireId,
    Value<int?>? professeurPrincipalId,
    Value<int?>? titulaireId,
    Value<int?>? surveillantGeneralId,
    Value<String>? nom,
    Value<String?>? sigle,
    Value<int?>? sousSystemeId,
    Value<String?>? niveauClasse,
    Value<String?>? filiere,
    Value<int?>? capacite,
    Value<String?>? qrToken,
  }) {
    return ClassesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      niveauId: niveauId ?? this.niveauId,
      niveauScolaireId: niveauScolaireId ?? this.niveauScolaireId,
      anneeScolaireId: anneeScolaireId ?? this.anneeScolaireId,
      professeurPrincipalId:
          professeurPrincipalId ?? this.professeurPrincipalId,
      titulaireId: titulaireId ?? this.titulaireId,
      surveillantGeneralId: surveillantGeneralId ?? this.surveillantGeneralId,
      nom: nom ?? this.nom,
      sigle: sigle ?? this.sigle,
      sousSystemeId: sousSystemeId ?? this.sousSystemeId,
      niveauClasse: niveauClasse ?? this.niveauClasse,
      filiere: filiere ?? this.filiere,
      capacite: capacite ?? this.capacite,
      qrToken: qrToken ?? this.qrToken,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (niveauId.present) {
      map['niveau_id'] = Variable<int>(niveauId.value);
    }
    if (niveauScolaireId.present) {
      map['niveau_scolaire_id'] = Variable<int>(niveauScolaireId.value);
    }
    if (anneeScolaireId.present) {
      map['annee_scolaire_id'] = Variable<int>(anneeScolaireId.value);
    }
    if (professeurPrincipalId.present) {
      map['professeur_principal_id'] = Variable<int>(
        professeurPrincipalId.value,
      );
    }
    if (titulaireId.present) {
      map['titulaire_id'] = Variable<int>(titulaireId.value);
    }
    if (surveillantGeneralId.present) {
      map['surveillant_general_id'] = Variable<int>(surveillantGeneralId.value);
    }
    if (nom.present) {
      map['nom'] = Variable<String>(nom.value);
    }
    if (sigle.present) {
      map['sigle'] = Variable<String>(sigle.value);
    }
    if (sousSystemeId.present) {
      map['sous_systeme_id'] = Variable<int>(sousSystemeId.value);
    }
    if (niveauClasse.present) {
      map['niveau_classe'] = Variable<String>(niveauClasse.value);
    }
    if (filiere.present) {
      map['filiere'] = Variable<String>(filiere.value);
    }
    if (capacite.present) {
      map['capacite'] = Variable<int>(capacite.value);
    }
    if (qrToken.present) {
      map['qr_token'] = Variable<String>(qrToken.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ClassesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('niveauId: $niveauId, ')
          ..write('niveauScolaireId: $niveauScolaireId, ')
          ..write('anneeScolaireId: $anneeScolaireId, ')
          ..write('professeurPrincipalId: $professeurPrincipalId, ')
          ..write('titulaireId: $titulaireId, ')
          ..write('surveillantGeneralId: $surveillantGeneralId, ')
          ..write('nom: $nom, ')
          ..write('sigle: $sigle, ')
          ..write('sousSystemeId: $sousSystemeId, ')
          ..write('niveauClasse: $niveauClasse, ')
          ..write('filiere: $filiere, ')
          ..write('capacite: $capacite, ')
          ..write('qrToken: $qrToken')
          ..write(')'))
        .toString();
  }
}

class $ClasseMatieresTable extends ClasseMatieres
    with TableInfo<$ClasseMatieresTable, ClasseMatiere> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ClasseMatieresTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _classeIdMeta = const VerificationMeta(
    'classeId',
  );
  @override
  late final GeneratedColumn<int> classeId = GeneratedColumn<int>(
    'classe_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _matiereIdMeta = const VerificationMeta(
    'matiereId',
  );
  @override
  late final GeneratedColumn<int> matiereId = GeneratedColumn<int>(
    'matiere_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _personnelIdMeta = const VerificationMeta(
    'personnelId',
  );
  @override
  late final GeneratedColumn<int> personnelId = GeneratedColumn<int>(
    'personnel_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _coefficientMeta = const VerificationMeta(
    'coefficient',
  );
  @override
  late final GeneratedColumn<double> coefficient = GeneratedColumn<double>(
    'coefficient',
    aliasedName,
    false,
    type: DriftSqlType.double,
    requiredDuringInsert: false,
    defaultValue: const Constant(1),
  );
  static const VerificationMeta _quotaHoraireMeta = const VerificationMeta(
    'quotaHoraire',
  );
  @override
  late final GeneratedColumn<int> quotaHoraire = GeneratedColumn<int>(
    'quota_horaire',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _groupeMeta = const VerificationMeta('groupe');
  @override
  late final GeneratedColumn<int> groupe = GeneratedColumn<int>(
    'groupe',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _competencesMeta = const VerificationMeta(
    'competences',
  );
  @override
  late final GeneratedColumn<String> competences = GeneratedColumn<String>(
    'competences',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _statutMeta = const VerificationMeta('statut');
  @override
  late final GeneratedColumn<String> statut = GeneratedColumn<String>(
    'statut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    classeId,
    matiereId,
    personnelId,
    coefficient,
    quotaHoraire,
    groupe,
    competences,
    statut,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'classe_matieres';
  @override
  VerificationContext validateIntegrity(
    Insertable<ClasseMatiere> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('classe_id')) {
      context.handle(
        _classeIdMeta,
        classeId.isAcceptableOrUnknown(data['classe_id']!, _classeIdMeta),
      );
    } else if (isInserting) {
      context.missing(_classeIdMeta);
    }
    if (data.containsKey('matiere_id')) {
      context.handle(
        _matiereIdMeta,
        matiereId.isAcceptableOrUnknown(data['matiere_id']!, _matiereIdMeta),
      );
    } else if (isInserting) {
      context.missing(_matiereIdMeta);
    }
    if (data.containsKey('personnel_id')) {
      context.handle(
        _personnelIdMeta,
        personnelId.isAcceptableOrUnknown(
          data['personnel_id']!,
          _personnelIdMeta,
        ),
      );
    }
    if (data.containsKey('coefficient')) {
      context.handle(
        _coefficientMeta,
        coefficient.isAcceptableOrUnknown(
          data['coefficient']!,
          _coefficientMeta,
        ),
      );
    }
    if (data.containsKey('quota_horaire')) {
      context.handle(
        _quotaHoraireMeta,
        quotaHoraire.isAcceptableOrUnknown(
          data['quota_horaire']!,
          _quotaHoraireMeta,
        ),
      );
    }
    if (data.containsKey('groupe')) {
      context.handle(
        _groupeMeta,
        groupe.isAcceptableOrUnknown(data['groupe']!, _groupeMeta),
      );
    }
    if (data.containsKey('competences')) {
      context.handle(
        _competencesMeta,
        competences.isAcceptableOrUnknown(
          data['competences']!,
          _competencesMeta,
        ),
      );
    }
    if (data.containsKey('statut')) {
      context.handle(
        _statutMeta,
        statut.isAcceptableOrUnknown(data['statut']!, _statutMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  ClasseMatiere map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return ClasseMatiere(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      classeId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_id'],
      )!,
      matiereId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}matiere_id'],
      )!,
      personnelId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}personnel_id'],
      ),
      coefficient: attachedDatabase.typeMapping.read(
        DriftSqlType.double,
        data['${effectivePrefix}coefficient'],
      )!,
      quotaHoraire: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}quota_horaire'],
      ),
      groupe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}groupe'],
      )!,
      competences: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}competences'],
      ),
      statut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}statut'],
      ),
    );
  }

  @override
  $ClasseMatieresTable createAlias(String alias) {
    return $ClasseMatieresTable(attachedDatabase, alias);
  }
}

class ClasseMatiere extends DataClass implements Insertable<ClasseMatiere> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int classeId;
  final int matiereId;
  final int? personnelId;
  final double coefficient;
  final int? quotaHoraire;
  final int groupe;
  final String? competences;
  final String? statut;
  const ClasseMatiere({
    required this.id,
    required this.etatSync,
    required this.classeId,
    required this.matiereId,
    this.personnelId,
    required this.coefficient,
    this.quotaHoraire,
    required this.groupe,
    this.competences,
    this.statut,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['classe_id'] = Variable<int>(classeId);
    map['matiere_id'] = Variable<int>(matiereId);
    if (!nullToAbsent || personnelId != null) {
      map['personnel_id'] = Variable<int>(personnelId);
    }
    map['coefficient'] = Variable<double>(coefficient);
    if (!nullToAbsent || quotaHoraire != null) {
      map['quota_horaire'] = Variable<int>(quotaHoraire);
    }
    map['groupe'] = Variable<int>(groupe);
    if (!nullToAbsent || competences != null) {
      map['competences'] = Variable<String>(competences);
    }
    if (!nullToAbsent || statut != null) {
      map['statut'] = Variable<String>(statut);
    }
    return map;
  }

  ClasseMatieresCompanion toCompanion(bool nullToAbsent) {
    return ClasseMatieresCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      classeId: Value(classeId),
      matiereId: Value(matiereId),
      personnelId: personnelId == null && nullToAbsent
          ? const Value.absent()
          : Value(personnelId),
      coefficient: Value(coefficient),
      quotaHoraire: quotaHoraire == null && nullToAbsent
          ? const Value.absent()
          : Value(quotaHoraire),
      groupe: Value(groupe),
      competences: competences == null && nullToAbsent
          ? const Value.absent()
          : Value(competences),
      statut: statut == null && nullToAbsent
          ? const Value.absent()
          : Value(statut),
    );
  }

  factory ClasseMatiere.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return ClasseMatiere(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      classeId: serializer.fromJson<int>(json['classeId']),
      matiereId: serializer.fromJson<int>(json['matiereId']),
      personnelId: serializer.fromJson<int?>(json['personnelId']),
      coefficient: serializer.fromJson<double>(json['coefficient']),
      quotaHoraire: serializer.fromJson<int?>(json['quotaHoraire']),
      groupe: serializer.fromJson<int>(json['groupe']),
      competences: serializer.fromJson<String?>(json['competences']),
      statut: serializer.fromJson<String?>(json['statut']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'classeId': serializer.toJson<int>(classeId),
      'matiereId': serializer.toJson<int>(matiereId),
      'personnelId': serializer.toJson<int?>(personnelId),
      'coefficient': serializer.toJson<double>(coefficient),
      'quotaHoraire': serializer.toJson<int?>(quotaHoraire),
      'groupe': serializer.toJson<int>(groupe),
      'competences': serializer.toJson<String?>(competences),
      'statut': serializer.toJson<String?>(statut),
    };
  }

  ClasseMatiere copyWith({
    int? id,
    String? etatSync,
    int? classeId,
    int? matiereId,
    Value<int?> personnelId = const Value.absent(),
    double? coefficient,
    Value<int?> quotaHoraire = const Value.absent(),
    int? groupe,
    Value<String?> competences = const Value.absent(),
    Value<String?> statut = const Value.absent(),
  }) => ClasseMatiere(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    classeId: classeId ?? this.classeId,
    matiereId: matiereId ?? this.matiereId,
    personnelId: personnelId.present ? personnelId.value : this.personnelId,
    coefficient: coefficient ?? this.coefficient,
    quotaHoraire: quotaHoraire.present ? quotaHoraire.value : this.quotaHoraire,
    groupe: groupe ?? this.groupe,
    competences: competences.present ? competences.value : this.competences,
    statut: statut.present ? statut.value : this.statut,
  );
  ClasseMatiere copyWithCompanion(ClasseMatieresCompanion data) {
    return ClasseMatiere(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      classeId: data.classeId.present ? data.classeId.value : this.classeId,
      matiereId: data.matiereId.present ? data.matiereId.value : this.matiereId,
      personnelId: data.personnelId.present
          ? data.personnelId.value
          : this.personnelId,
      coefficient: data.coefficient.present
          ? data.coefficient.value
          : this.coefficient,
      quotaHoraire: data.quotaHoraire.present
          ? data.quotaHoraire.value
          : this.quotaHoraire,
      groupe: data.groupe.present ? data.groupe.value : this.groupe,
      competences: data.competences.present
          ? data.competences.value
          : this.competences,
      statut: data.statut.present ? data.statut.value : this.statut,
    );
  }

  @override
  String toString() {
    return (StringBuffer('ClasseMatiere(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('classeId: $classeId, ')
          ..write('matiereId: $matiereId, ')
          ..write('personnelId: $personnelId, ')
          ..write('coefficient: $coefficient, ')
          ..write('quotaHoraire: $quotaHoraire, ')
          ..write('groupe: $groupe, ')
          ..write('competences: $competences, ')
          ..write('statut: $statut')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    classeId,
    matiereId,
    personnelId,
    coefficient,
    quotaHoraire,
    groupe,
    competences,
    statut,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ClasseMatiere &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.classeId == this.classeId &&
          other.matiereId == this.matiereId &&
          other.personnelId == this.personnelId &&
          other.coefficient == this.coefficient &&
          other.quotaHoraire == this.quotaHoraire &&
          other.groupe == this.groupe &&
          other.competences == this.competences &&
          other.statut == this.statut);
}

class ClasseMatieresCompanion extends UpdateCompanion<ClasseMatiere> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> classeId;
  final Value<int> matiereId;
  final Value<int?> personnelId;
  final Value<double> coefficient;
  final Value<int?> quotaHoraire;
  final Value<int> groupe;
  final Value<String?> competences;
  final Value<String?> statut;
  const ClasseMatieresCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.classeId = const Value.absent(),
    this.matiereId = const Value.absent(),
    this.personnelId = const Value.absent(),
    this.coefficient = const Value.absent(),
    this.quotaHoraire = const Value.absent(),
    this.groupe = const Value.absent(),
    this.competences = const Value.absent(),
    this.statut = const Value.absent(),
  });
  ClasseMatieresCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int classeId,
    required int matiereId,
    this.personnelId = const Value.absent(),
    this.coefficient = const Value.absent(),
    this.quotaHoraire = const Value.absent(),
    this.groupe = const Value.absent(),
    this.competences = const Value.absent(),
    this.statut = const Value.absent(),
  }) : classeId = Value(classeId),
       matiereId = Value(matiereId);
  static Insertable<ClasseMatiere> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? classeId,
    Expression<int>? matiereId,
    Expression<int>? personnelId,
    Expression<double>? coefficient,
    Expression<int>? quotaHoraire,
    Expression<int>? groupe,
    Expression<String>? competences,
    Expression<String>? statut,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (classeId != null) 'classe_id': classeId,
      if (matiereId != null) 'matiere_id': matiereId,
      if (personnelId != null) 'personnel_id': personnelId,
      if (coefficient != null) 'coefficient': coefficient,
      if (quotaHoraire != null) 'quota_horaire': quotaHoraire,
      if (groupe != null) 'groupe': groupe,
      if (competences != null) 'competences': competences,
      if (statut != null) 'statut': statut,
    });
  }

  ClasseMatieresCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? classeId,
    Value<int>? matiereId,
    Value<int?>? personnelId,
    Value<double>? coefficient,
    Value<int?>? quotaHoraire,
    Value<int>? groupe,
    Value<String?>? competences,
    Value<String?>? statut,
  }) {
    return ClasseMatieresCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      classeId: classeId ?? this.classeId,
      matiereId: matiereId ?? this.matiereId,
      personnelId: personnelId ?? this.personnelId,
      coefficient: coefficient ?? this.coefficient,
      quotaHoraire: quotaHoraire ?? this.quotaHoraire,
      groupe: groupe ?? this.groupe,
      competences: competences ?? this.competences,
      statut: statut ?? this.statut,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (classeId.present) {
      map['classe_id'] = Variable<int>(classeId.value);
    }
    if (matiereId.present) {
      map['matiere_id'] = Variable<int>(matiereId.value);
    }
    if (personnelId.present) {
      map['personnel_id'] = Variable<int>(personnelId.value);
    }
    if (coefficient.present) {
      map['coefficient'] = Variable<double>(coefficient.value);
    }
    if (quotaHoraire.present) {
      map['quota_horaire'] = Variable<int>(quotaHoraire.value);
    }
    if (groupe.present) {
      map['groupe'] = Variable<int>(groupe.value);
    }
    if (competences.present) {
      map['competences'] = Variable<String>(competences.value);
    }
    if (statut.present) {
      map['statut'] = Variable<String>(statut.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ClasseMatieresCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('classeId: $classeId, ')
          ..write('matiereId: $matiereId, ')
          ..write('personnelId: $personnelId, ')
          ..write('coefficient: $coefficient, ')
          ..write('quotaHoraire: $quotaHoraire, ')
          ..write('groupe: $groupe, ')
          ..write('competences: $competences, ')
          ..write('statut: $statut')
          ..write(')'))
        .toString();
  }
}

class $EmploisDuTempsTable extends EmploisDuTemps
    with TableInfo<$EmploisDuTempsTable, EmploisDuTemp> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $EmploisDuTempsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _classeIdMeta = const VerificationMeta(
    'classeId',
  );
  @override
  late final GeneratedColumn<int> classeId = GeneratedColumn<int>(
    'classe_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _classeMatiereIdMeta = const VerificationMeta(
    'classeMatiereId',
  );
  @override
  late final GeneratedColumn<int> classeMatiereId = GeneratedColumn<int>(
    'classe_matiere_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _jourMeta = const VerificationMeta('jour');
  @override
  late final GeneratedColumn<String> jour = GeneratedColumn<String>(
    'jour',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _heureDebutMeta = const VerificationMeta(
    'heureDebut',
  );
  @override
  late final GeneratedColumn<String> heureDebut = GeneratedColumn<String>(
    'heure_debut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _heureFinMeta = const VerificationMeta(
    'heureFin',
  );
  @override
  late final GeneratedColumn<String> heureFin = GeneratedColumn<String>(
    'heure_fin',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _salleMeta = const VerificationMeta('salle');
  @override
  late final GeneratedColumn<String> salle = GeneratedColumn<String>(
    'salle',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    classeId,
    classeMatiereId,
    jour,
    heureDebut,
    heureFin,
    salle,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'emplois_du_temps';
  @override
  VerificationContext validateIntegrity(
    Insertable<EmploisDuTemp> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('classe_id')) {
      context.handle(
        _classeIdMeta,
        classeId.isAcceptableOrUnknown(data['classe_id']!, _classeIdMeta),
      );
    } else if (isInserting) {
      context.missing(_classeIdMeta);
    }
    if (data.containsKey('classe_matiere_id')) {
      context.handle(
        _classeMatiereIdMeta,
        classeMatiereId.isAcceptableOrUnknown(
          data['classe_matiere_id']!,
          _classeMatiereIdMeta,
        ),
      );
    }
    if (data.containsKey('jour')) {
      context.handle(
        _jourMeta,
        jour.isAcceptableOrUnknown(data['jour']!, _jourMeta),
      );
    }
    if (data.containsKey('heure_debut')) {
      context.handle(
        _heureDebutMeta,
        heureDebut.isAcceptableOrUnknown(data['heure_debut']!, _heureDebutMeta),
      );
    }
    if (data.containsKey('heure_fin')) {
      context.handle(
        _heureFinMeta,
        heureFin.isAcceptableOrUnknown(data['heure_fin']!, _heureFinMeta),
      );
    }
    if (data.containsKey('salle')) {
      context.handle(
        _salleMeta,
        salle.isAcceptableOrUnknown(data['salle']!, _salleMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  EmploisDuTemp map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return EmploisDuTemp(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      classeId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_id'],
      )!,
      classeMatiereId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_matiere_id'],
      ),
      jour: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}jour'],
      ),
      heureDebut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}heure_debut'],
      ),
      heureFin: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}heure_fin'],
      ),
      salle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}salle'],
      ),
    );
  }

  @override
  $EmploisDuTempsTable createAlias(String alias) {
    return $EmploisDuTempsTable(attachedDatabase, alias);
  }
}

class EmploisDuTemp extends DataClass implements Insertable<EmploisDuTemp> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final int classeId;
  final int? classeMatiereId;
  final String? jour;
  final String? heureDebut;
  final String? heureFin;
  final String? salle;
  const EmploisDuTemp({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    required this.classeId,
    this.classeMatiereId,
    this.jour,
    this.heureDebut,
    this.heureFin,
    this.salle,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    map['classe_id'] = Variable<int>(classeId);
    if (!nullToAbsent || classeMatiereId != null) {
      map['classe_matiere_id'] = Variable<int>(classeMatiereId);
    }
    if (!nullToAbsent || jour != null) {
      map['jour'] = Variable<String>(jour);
    }
    if (!nullToAbsent || heureDebut != null) {
      map['heure_debut'] = Variable<String>(heureDebut);
    }
    if (!nullToAbsent || heureFin != null) {
      map['heure_fin'] = Variable<String>(heureFin);
    }
    if (!nullToAbsent || salle != null) {
      map['salle'] = Variable<String>(salle);
    }
    return map;
  }

  EmploisDuTempsCompanion toCompanion(bool nullToAbsent) {
    return EmploisDuTempsCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      classeId: Value(classeId),
      classeMatiereId: classeMatiereId == null && nullToAbsent
          ? const Value.absent()
          : Value(classeMatiereId),
      jour: jour == null && nullToAbsent ? const Value.absent() : Value(jour),
      heureDebut: heureDebut == null && nullToAbsent
          ? const Value.absent()
          : Value(heureDebut),
      heureFin: heureFin == null && nullToAbsent
          ? const Value.absent()
          : Value(heureFin),
      salle: salle == null && nullToAbsent
          ? const Value.absent()
          : Value(salle),
    );
  }

  factory EmploisDuTemp.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return EmploisDuTemp(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      classeId: serializer.fromJson<int>(json['classeId']),
      classeMatiereId: serializer.fromJson<int?>(json['classeMatiereId']),
      jour: serializer.fromJson<String?>(json['jour']),
      heureDebut: serializer.fromJson<String?>(json['heureDebut']),
      heureFin: serializer.fromJson<String?>(json['heureFin']),
      salle: serializer.fromJson<String?>(json['salle']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'classeId': serializer.toJson<int>(classeId),
      'classeMatiereId': serializer.toJson<int?>(classeMatiereId),
      'jour': serializer.toJson<String?>(jour),
      'heureDebut': serializer.toJson<String?>(heureDebut),
      'heureFin': serializer.toJson<String?>(heureFin),
      'salle': serializer.toJson<String?>(salle),
    };
  }

  EmploisDuTemp copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    int? classeId,
    Value<int?> classeMatiereId = const Value.absent(),
    Value<String?> jour = const Value.absent(),
    Value<String?> heureDebut = const Value.absent(),
    Value<String?> heureFin = const Value.absent(),
    Value<String?> salle = const Value.absent(),
  }) => EmploisDuTemp(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    classeId: classeId ?? this.classeId,
    classeMatiereId: classeMatiereId.present
        ? classeMatiereId.value
        : this.classeMatiereId,
    jour: jour.present ? jour.value : this.jour,
    heureDebut: heureDebut.present ? heureDebut.value : this.heureDebut,
    heureFin: heureFin.present ? heureFin.value : this.heureFin,
    salle: salle.present ? salle.value : this.salle,
  );
  EmploisDuTemp copyWithCompanion(EmploisDuTempsCompanion data) {
    return EmploisDuTemp(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      classeId: data.classeId.present ? data.classeId.value : this.classeId,
      classeMatiereId: data.classeMatiereId.present
          ? data.classeMatiereId.value
          : this.classeMatiereId,
      jour: data.jour.present ? data.jour.value : this.jour,
      heureDebut: data.heureDebut.present
          ? data.heureDebut.value
          : this.heureDebut,
      heureFin: data.heureFin.present ? data.heureFin.value : this.heureFin,
      salle: data.salle.present ? data.salle.value : this.salle,
    );
  }

  @override
  String toString() {
    return (StringBuffer('EmploisDuTemp(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('classeId: $classeId, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('jour: $jour, ')
          ..write('heureDebut: $heureDebut, ')
          ..write('heureFin: $heureFin, ')
          ..write('salle: $salle')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    classeId,
    classeMatiereId,
    jour,
    heureDebut,
    heureFin,
    salle,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is EmploisDuTemp &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.classeId == this.classeId &&
          other.classeMatiereId == this.classeMatiereId &&
          other.jour == this.jour &&
          other.heureDebut == this.heureDebut &&
          other.heureFin == this.heureFin &&
          other.salle == this.salle);
}

class EmploisDuTempsCompanion extends UpdateCompanion<EmploisDuTemp> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<int> classeId;
  final Value<int?> classeMatiereId;
  final Value<String?> jour;
  final Value<String?> heureDebut;
  final Value<String?> heureFin;
  final Value<String?> salle;
  const EmploisDuTempsCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.classeId = const Value.absent(),
    this.classeMatiereId = const Value.absent(),
    this.jour = const Value.absent(),
    this.heureDebut = const Value.absent(),
    this.heureFin = const Value.absent(),
    this.salle = const Value.absent(),
  });
  EmploisDuTempsCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    required int classeId,
    this.classeMatiereId = const Value.absent(),
    this.jour = const Value.absent(),
    this.heureDebut = const Value.absent(),
    this.heureFin = const Value.absent(),
    this.salle = const Value.absent(),
  }) : schoolId = Value(schoolId),
       classeId = Value(classeId);
  static Insertable<EmploisDuTemp> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<int>? classeId,
    Expression<int>? classeMatiereId,
    Expression<String>? jour,
    Expression<String>? heureDebut,
    Expression<String>? heureFin,
    Expression<String>? salle,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (classeId != null) 'classe_id': classeId,
      if (classeMatiereId != null) 'classe_matiere_id': classeMatiereId,
      if (jour != null) 'jour': jour,
      if (heureDebut != null) 'heure_debut': heureDebut,
      if (heureFin != null) 'heure_fin': heureFin,
      if (salle != null) 'salle': salle,
    });
  }

  EmploisDuTempsCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<int>? classeId,
    Value<int?>? classeMatiereId,
    Value<String?>? jour,
    Value<String?>? heureDebut,
    Value<String?>? heureFin,
    Value<String?>? salle,
  }) {
    return EmploisDuTempsCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      classeId: classeId ?? this.classeId,
      classeMatiereId: classeMatiereId ?? this.classeMatiereId,
      jour: jour ?? this.jour,
      heureDebut: heureDebut ?? this.heureDebut,
      heureFin: heureFin ?? this.heureFin,
      salle: salle ?? this.salle,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (classeId.present) {
      map['classe_id'] = Variable<int>(classeId.value);
    }
    if (classeMatiereId.present) {
      map['classe_matiere_id'] = Variable<int>(classeMatiereId.value);
    }
    if (jour.present) {
      map['jour'] = Variable<String>(jour.value);
    }
    if (heureDebut.present) {
      map['heure_debut'] = Variable<String>(heureDebut.value);
    }
    if (heureFin.present) {
      map['heure_fin'] = Variable<String>(heureFin.value);
    }
    if (salle.present) {
      map['salle'] = Variable<String>(salle.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('EmploisDuTempsCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('classeId: $classeId, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('jour: $jour, ')
          ..write('heureDebut: $heureDebut, ')
          ..write('heureFin: $heureFin, ')
          ..write('salle: $salle')
          ..write(')'))
        .toString();
  }
}

class $ProgressionItemsTable extends ProgressionItems
    with TableInfo<$ProgressionItemsTable, ProgressionItem> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ProgressionItemsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _classeMatiereIdMeta = const VerificationMeta(
    'classeMatiereId',
  );
  @override
  late final GeneratedColumn<int> classeMatiereId = GeneratedColumn<int>(
    'classe_matiere_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _parentIdMeta = const VerificationMeta(
    'parentId',
  );
  @override
  late final GeneratedColumn<int> parentId = GeneratedColumn<int>(
    'parent_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _typeMeta = const VerificationMeta('type');
  @override
  late final GeneratedColumn<String> type = GeneratedColumn<String>(
    'type',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _titreMeta = const VerificationMeta('titre');
  @override
  late final GeneratedColumn<String> titre = GeneratedColumn<String>(
    'titre',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _descriptionMeta = const VerificationMeta(
    'description',
  );
  @override
  late final GeneratedColumn<String> description = GeneratedColumn<String>(
    'description',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _objectifsMeta = const VerificationMeta(
    'objectifs',
  );
  @override
  late final GeneratedColumn<String> objectifs = GeneratedColumn<String>(
    'objectifs',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _materielMeta = const VerificationMeta(
    'materiel',
  );
  @override
  late final GeneratedColumn<String> materiel = GeneratedColumn<String>(
    'materiel',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _activitesMeta = const VerificationMeta(
    'activites',
  );
  @override
  late final GeneratedColumn<String> activites = GeneratedColumn<String>(
    'activites',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _devoirsMeta = const VerificationMeta(
    'devoirs',
  );
  @override
  late final GeneratedColumn<String> devoirs = GeneratedColumn<String>(
    'devoirs',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _ordreMeta = const VerificationMeta('ordre');
  @override
  late final GeneratedColumn<int> ordre = GeneratedColumn<int>(
    'ordre',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _sequenceIdMeta = const VerificationMeta(
    'sequenceId',
  );
  @override
  late final GeneratedColumn<int> sequenceId = GeneratedColumn<int>(
    'sequence_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dureePrevueMeta = const VerificationMeta(
    'dureePrevue',
  );
  @override
  late final GeneratedColumn<int> dureePrevue = GeneratedColumn<int>(
    'duree_prevue',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    classeMatiereId,
    parentId,
    type,
    titre,
    description,
    objectifs,
    materiel,
    activites,
    devoirs,
    ordre,
    sequenceId,
    dureePrevue,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'progression_items';
  @override
  VerificationContext validateIntegrity(
    Insertable<ProgressionItem> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('classe_matiere_id')) {
      context.handle(
        _classeMatiereIdMeta,
        classeMatiereId.isAcceptableOrUnknown(
          data['classe_matiere_id']!,
          _classeMatiereIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_classeMatiereIdMeta);
    }
    if (data.containsKey('parent_id')) {
      context.handle(
        _parentIdMeta,
        parentId.isAcceptableOrUnknown(data['parent_id']!, _parentIdMeta),
      );
    }
    if (data.containsKey('type')) {
      context.handle(
        _typeMeta,
        type.isAcceptableOrUnknown(data['type']!, _typeMeta),
      );
    }
    if (data.containsKey('titre')) {
      context.handle(
        _titreMeta,
        titre.isAcceptableOrUnknown(data['titre']!, _titreMeta),
      );
    } else if (isInserting) {
      context.missing(_titreMeta);
    }
    if (data.containsKey('description')) {
      context.handle(
        _descriptionMeta,
        description.isAcceptableOrUnknown(
          data['description']!,
          _descriptionMeta,
        ),
      );
    }
    if (data.containsKey('objectifs')) {
      context.handle(
        _objectifsMeta,
        objectifs.isAcceptableOrUnknown(data['objectifs']!, _objectifsMeta),
      );
    }
    if (data.containsKey('materiel')) {
      context.handle(
        _materielMeta,
        materiel.isAcceptableOrUnknown(data['materiel']!, _materielMeta),
      );
    }
    if (data.containsKey('activites')) {
      context.handle(
        _activitesMeta,
        activites.isAcceptableOrUnknown(data['activites']!, _activitesMeta),
      );
    }
    if (data.containsKey('devoirs')) {
      context.handle(
        _devoirsMeta,
        devoirs.isAcceptableOrUnknown(data['devoirs']!, _devoirsMeta),
      );
    }
    if (data.containsKey('ordre')) {
      context.handle(
        _ordreMeta,
        ordre.isAcceptableOrUnknown(data['ordre']!, _ordreMeta),
      );
    }
    if (data.containsKey('sequence_id')) {
      context.handle(
        _sequenceIdMeta,
        sequenceId.isAcceptableOrUnknown(data['sequence_id']!, _sequenceIdMeta),
      );
    }
    if (data.containsKey('duree_prevue')) {
      context.handle(
        _dureePrevueMeta,
        dureePrevue.isAcceptableOrUnknown(
          data['duree_prevue']!,
          _dureePrevueMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  ProgressionItem map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return ProgressionItem(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      classeMatiereId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_matiere_id'],
      )!,
      parentId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}parent_id'],
      ),
      type: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}type'],
      ),
      titre: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}titre'],
      )!,
      description: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}description'],
      ),
      objectifs: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}objectifs'],
      ),
      materiel: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}materiel'],
      ),
      activites: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}activites'],
      ),
      devoirs: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}devoirs'],
      ),
      ordre: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ordre'],
      )!,
      sequenceId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}sequence_id'],
      ),
      dureePrevue: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}duree_prevue'],
      ),
    );
  }

  @override
  $ProgressionItemsTable createAlias(String alias) {
    return $ProgressionItemsTable(attachedDatabase, alias);
  }
}

class ProgressionItem extends DataClass implements Insertable<ProgressionItem> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int classeMatiereId;
  final int? parentId;
  final String? type;
  final String titre;
  final String? description;
  final String? objectifs;
  final String? materiel;
  final String? activites;
  final String? devoirs;
  final int ordre;
  final int? sequenceId;
  final int? dureePrevue;
  const ProgressionItem({
    required this.id,
    required this.etatSync,
    required this.classeMatiereId,
    this.parentId,
    this.type,
    required this.titre,
    this.description,
    this.objectifs,
    this.materiel,
    this.activites,
    this.devoirs,
    required this.ordre,
    this.sequenceId,
    this.dureePrevue,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['classe_matiere_id'] = Variable<int>(classeMatiereId);
    if (!nullToAbsent || parentId != null) {
      map['parent_id'] = Variable<int>(parentId);
    }
    if (!nullToAbsent || type != null) {
      map['type'] = Variable<String>(type);
    }
    map['titre'] = Variable<String>(titre);
    if (!nullToAbsent || description != null) {
      map['description'] = Variable<String>(description);
    }
    if (!nullToAbsent || objectifs != null) {
      map['objectifs'] = Variable<String>(objectifs);
    }
    if (!nullToAbsent || materiel != null) {
      map['materiel'] = Variable<String>(materiel);
    }
    if (!nullToAbsent || activites != null) {
      map['activites'] = Variable<String>(activites);
    }
    if (!nullToAbsent || devoirs != null) {
      map['devoirs'] = Variable<String>(devoirs);
    }
    map['ordre'] = Variable<int>(ordre);
    if (!nullToAbsent || sequenceId != null) {
      map['sequence_id'] = Variable<int>(sequenceId);
    }
    if (!nullToAbsent || dureePrevue != null) {
      map['duree_prevue'] = Variable<int>(dureePrevue);
    }
    return map;
  }

  ProgressionItemsCompanion toCompanion(bool nullToAbsent) {
    return ProgressionItemsCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      classeMatiereId: Value(classeMatiereId),
      parentId: parentId == null && nullToAbsent
          ? const Value.absent()
          : Value(parentId),
      type: type == null && nullToAbsent ? const Value.absent() : Value(type),
      titre: Value(titre),
      description: description == null && nullToAbsent
          ? const Value.absent()
          : Value(description),
      objectifs: objectifs == null && nullToAbsent
          ? const Value.absent()
          : Value(objectifs),
      materiel: materiel == null && nullToAbsent
          ? const Value.absent()
          : Value(materiel),
      activites: activites == null && nullToAbsent
          ? const Value.absent()
          : Value(activites),
      devoirs: devoirs == null && nullToAbsent
          ? const Value.absent()
          : Value(devoirs),
      ordre: Value(ordre),
      sequenceId: sequenceId == null && nullToAbsent
          ? const Value.absent()
          : Value(sequenceId),
      dureePrevue: dureePrevue == null && nullToAbsent
          ? const Value.absent()
          : Value(dureePrevue),
    );
  }

  factory ProgressionItem.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return ProgressionItem(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      classeMatiereId: serializer.fromJson<int>(json['classeMatiereId']),
      parentId: serializer.fromJson<int?>(json['parentId']),
      type: serializer.fromJson<String?>(json['type']),
      titre: serializer.fromJson<String>(json['titre']),
      description: serializer.fromJson<String?>(json['description']),
      objectifs: serializer.fromJson<String?>(json['objectifs']),
      materiel: serializer.fromJson<String?>(json['materiel']),
      activites: serializer.fromJson<String?>(json['activites']),
      devoirs: serializer.fromJson<String?>(json['devoirs']),
      ordre: serializer.fromJson<int>(json['ordre']),
      sequenceId: serializer.fromJson<int?>(json['sequenceId']),
      dureePrevue: serializer.fromJson<int?>(json['dureePrevue']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'classeMatiereId': serializer.toJson<int>(classeMatiereId),
      'parentId': serializer.toJson<int?>(parentId),
      'type': serializer.toJson<String?>(type),
      'titre': serializer.toJson<String>(titre),
      'description': serializer.toJson<String?>(description),
      'objectifs': serializer.toJson<String?>(objectifs),
      'materiel': serializer.toJson<String?>(materiel),
      'activites': serializer.toJson<String?>(activites),
      'devoirs': serializer.toJson<String?>(devoirs),
      'ordre': serializer.toJson<int>(ordre),
      'sequenceId': serializer.toJson<int?>(sequenceId),
      'dureePrevue': serializer.toJson<int?>(dureePrevue),
    };
  }

  ProgressionItem copyWith({
    int? id,
    String? etatSync,
    int? classeMatiereId,
    Value<int?> parentId = const Value.absent(),
    Value<String?> type = const Value.absent(),
    String? titre,
    Value<String?> description = const Value.absent(),
    Value<String?> objectifs = const Value.absent(),
    Value<String?> materiel = const Value.absent(),
    Value<String?> activites = const Value.absent(),
    Value<String?> devoirs = const Value.absent(),
    int? ordre,
    Value<int?> sequenceId = const Value.absent(),
    Value<int?> dureePrevue = const Value.absent(),
  }) => ProgressionItem(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    classeMatiereId: classeMatiereId ?? this.classeMatiereId,
    parentId: parentId.present ? parentId.value : this.parentId,
    type: type.present ? type.value : this.type,
    titre: titre ?? this.titre,
    description: description.present ? description.value : this.description,
    objectifs: objectifs.present ? objectifs.value : this.objectifs,
    materiel: materiel.present ? materiel.value : this.materiel,
    activites: activites.present ? activites.value : this.activites,
    devoirs: devoirs.present ? devoirs.value : this.devoirs,
    ordre: ordre ?? this.ordre,
    sequenceId: sequenceId.present ? sequenceId.value : this.sequenceId,
    dureePrevue: dureePrevue.present ? dureePrevue.value : this.dureePrevue,
  );
  ProgressionItem copyWithCompanion(ProgressionItemsCompanion data) {
    return ProgressionItem(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      classeMatiereId: data.classeMatiereId.present
          ? data.classeMatiereId.value
          : this.classeMatiereId,
      parentId: data.parentId.present ? data.parentId.value : this.parentId,
      type: data.type.present ? data.type.value : this.type,
      titre: data.titre.present ? data.titre.value : this.titre,
      description: data.description.present
          ? data.description.value
          : this.description,
      objectifs: data.objectifs.present ? data.objectifs.value : this.objectifs,
      materiel: data.materiel.present ? data.materiel.value : this.materiel,
      activites: data.activites.present ? data.activites.value : this.activites,
      devoirs: data.devoirs.present ? data.devoirs.value : this.devoirs,
      ordre: data.ordre.present ? data.ordre.value : this.ordre,
      sequenceId: data.sequenceId.present
          ? data.sequenceId.value
          : this.sequenceId,
      dureePrevue: data.dureePrevue.present
          ? data.dureePrevue.value
          : this.dureePrevue,
    );
  }

  @override
  String toString() {
    return (StringBuffer('ProgressionItem(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('parentId: $parentId, ')
          ..write('type: $type, ')
          ..write('titre: $titre, ')
          ..write('description: $description, ')
          ..write('objectifs: $objectifs, ')
          ..write('materiel: $materiel, ')
          ..write('activites: $activites, ')
          ..write('devoirs: $devoirs, ')
          ..write('ordre: $ordre, ')
          ..write('sequenceId: $sequenceId, ')
          ..write('dureePrevue: $dureePrevue')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    classeMatiereId,
    parentId,
    type,
    titre,
    description,
    objectifs,
    materiel,
    activites,
    devoirs,
    ordre,
    sequenceId,
    dureePrevue,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ProgressionItem &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.classeMatiereId == this.classeMatiereId &&
          other.parentId == this.parentId &&
          other.type == this.type &&
          other.titre == this.titre &&
          other.description == this.description &&
          other.objectifs == this.objectifs &&
          other.materiel == this.materiel &&
          other.activites == this.activites &&
          other.devoirs == this.devoirs &&
          other.ordre == this.ordre &&
          other.sequenceId == this.sequenceId &&
          other.dureePrevue == this.dureePrevue);
}

class ProgressionItemsCompanion extends UpdateCompanion<ProgressionItem> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> classeMatiereId;
  final Value<int?> parentId;
  final Value<String?> type;
  final Value<String> titre;
  final Value<String?> description;
  final Value<String?> objectifs;
  final Value<String?> materiel;
  final Value<String?> activites;
  final Value<String?> devoirs;
  final Value<int> ordre;
  final Value<int?> sequenceId;
  final Value<int?> dureePrevue;
  const ProgressionItemsCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.classeMatiereId = const Value.absent(),
    this.parentId = const Value.absent(),
    this.type = const Value.absent(),
    this.titre = const Value.absent(),
    this.description = const Value.absent(),
    this.objectifs = const Value.absent(),
    this.materiel = const Value.absent(),
    this.activites = const Value.absent(),
    this.devoirs = const Value.absent(),
    this.ordre = const Value.absent(),
    this.sequenceId = const Value.absent(),
    this.dureePrevue = const Value.absent(),
  });
  ProgressionItemsCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int classeMatiereId,
    this.parentId = const Value.absent(),
    this.type = const Value.absent(),
    required String titre,
    this.description = const Value.absent(),
    this.objectifs = const Value.absent(),
    this.materiel = const Value.absent(),
    this.activites = const Value.absent(),
    this.devoirs = const Value.absent(),
    this.ordre = const Value.absent(),
    this.sequenceId = const Value.absent(),
    this.dureePrevue = const Value.absent(),
  }) : classeMatiereId = Value(classeMatiereId),
       titre = Value(titre);
  static Insertable<ProgressionItem> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? classeMatiereId,
    Expression<int>? parentId,
    Expression<String>? type,
    Expression<String>? titre,
    Expression<String>? description,
    Expression<String>? objectifs,
    Expression<String>? materiel,
    Expression<String>? activites,
    Expression<String>? devoirs,
    Expression<int>? ordre,
    Expression<int>? sequenceId,
    Expression<int>? dureePrevue,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (classeMatiereId != null) 'classe_matiere_id': classeMatiereId,
      if (parentId != null) 'parent_id': parentId,
      if (type != null) 'type': type,
      if (titre != null) 'titre': titre,
      if (description != null) 'description': description,
      if (objectifs != null) 'objectifs': objectifs,
      if (materiel != null) 'materiel': materiel,
      if (activites != null) 'activites': activites,
      if (devoirs != null) 'devoirs': devoirs,
      if (ordre != null) 'ordre': ordre,
      if (sequenceId != null) 'sequence_id': sequenceId,
      if (dureePrevue != null) 'duree_prevue': dureePrevue,
    });
  }

  ProgressionItemsCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? classeMatiereId,
    Value<int?>? parentId,
    Value<String?>? type,
    Value<String>? titre,
    Value<String?>? description,
    Value<String?>? objectifs,
    Value<String?>? materiel,
    Value<String?>? activites,
    Value<String?>? devoirs,
    Value<int>? ordre,
    Value<int?>? sequenceId,
    Value<int?>? dureePrevue,
  }) {
    return ProgressionItemsCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      classeMatiereId: classeMatiereId ?? this.classeMatiereId,
      parentId: parentId ?? this.parentId,
      type: type ?? this.type,
      titre: titre ?? this.titre,
      description: description ?? this.description,
      objectifs: objectifs ?? this.objectifs,
      materiel: materiel ?? this.materiel,
      activites: activites ?? this.activites,
      devoirs: devoirs ?? this.devoirs,
      ordre: ordre ?? this.ordre,
      sequenceId: sequenceId ?? this.sequenceId,
      dureePrevue: dureePrevue ?? this.dureePrevue,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (classeMatiereId.present) {
      map['classe_matiere_id'] = Variable<int>(classeMatiereId.value);
    }
    if (parentId.present) {
      map['parent_id'] = Variable<int>(parentId.value);
    }
    if (type.present) {
      map['type'] = Variable<String>(type.value);
    }
    if (titre.present) {
      map['titre'] = Variable<String>(titre.value);
    }
    if (description.present) {
      map['description'] = Variable<String>(description.value);
    }
    if (objectifs.present) {
      map['objectifs'] = Variable<String>(objectifs.value);
    }
    if (materiel.present) {
      map['materiel'] = Variable<String>(materiel.value);
    }
    if (activites.present) {
      map['activites'] = Variable<String>(activites.value);
    }
    if (devoirs.present) {
      map['devoirs'] = Variable<String>(devoirs.value);
    }
    if (ordre.present) {
      map['ordre'] = Variable<int>(ordre.value);
    }
    if (sequenceId.present) {
      map['sequence_id'] = Variable<int>(sequenceId.value);
    }
    if (dureePrevue.present) {
      map['duree_prevue'] = Variable<int>(dureePrevue.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ProgressionItemsCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('parentId: $parentId, ')
          ..write('type: $type, ')
          ..write('titre: $titre, ')
          ..write('description: $description, ')
          ..write('objectifs: $objectifs, ')
          ..write('materiel: $materiel, ')
          ..write('activites: $activites, ')
          ..write('devoirs: $devoirs, ')
          ..write('ordre: $ordre, ')
          ..write('sequenceId: $sequenceId, ')
          ..write('dureePrevue: $dureePrevue')
          ..write(')'))
        .toString();
  }
}

class $ElevesTable extends Eleves with TableInfo<$ElevesTable, Eleve> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ElevesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _classeIdMeta = const VerificationMeta(
    'classeId',
  );
  @override
  late final GeneratedColumn<int> classeId = GeneratedColumn<int>(
    'classe_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _matriculeMeta = const VerificationMeta(
    'matricule',
  );
  @override
  late final GeneratedColumn<String> matricule = GeneratedColumn<String>(
    'matricule',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _nomCompletMeta = const VerificationMeta(
    'nomComplet',
  );
  @override
  late final GeneratedColumn<String> nomComplet = GeneratedColumn<String>(
    'nom_complet',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _sexeMeta = const VerificationMeta('sexe');
  @override
  late final GeneratedColumn<String> sexe = GeneratedColumn<String>(
    'sexe',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dateNaissanceMeta = const VerificationMeta(
    'dateNaissance',
  );
  @override
  late final GeneratedColumn<String> dateNaissance = GeneratedColumn<String>(
    'date_naissance',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _lieuNaissanceMeta = const VerificationMeta(
    'lieuNaissance',
  );
  @override
  late final GeneratedColumn<String> lieuNaissance = GeneratedColumn<String>(
    'lieu_naissance',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _nationaliteMeta = const VerificationMeta(
    'nationalite',
  );
  @override
  late final GeneratedColumn<String> nationalite = GeneratedColumn<String>(
    'nationalite',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _redoublantMeta = const VerificationMeta(
    'redoublant',
  );
  @override
  late final GeneratedColumn<bool> redoublant = GeneratedColumn<bool>(
    'redoublant',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("redoublant" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _statutMeta = const VerificationMeta('statut');
  @override
  late final GeneratedColumn<String> statut = GeneratedColumn<String>(
    'statut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _photoPathMeta = const VerificationMeta(
    'photoPath',
  );
  @override
  late final GeneratedColumn<String> photoPath = GeneratedColumn<String>(
    'photo_path',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    classeId,
    matricule,
    nomComplet,
    sexe,
    dateNaissance,
    lieuNaissance,
    nationalite,
    redoublant,
    statut,
    photoPath,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'eleves';
  @override
  VerificationContext validateIntegrity(
    Insertable<Eleve> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('classe_id')) {
      context.handle(
        _classeIdMeta,
        classeId.isAcceptableOrUnknown(data['classe_id']!, _classeIdMeta),
      );
    }
    if (data.containsKey('matricule')) {
      context.handle(
        _matriculeMeta,
        matricule.isAcceptableOrUnknown(data['matricule']!, _matriculeMeta),
      );
    }
    if (data.containsKey('nom_complet')) {
      context.handle(
        _nomCompletMeta,
        nomComplet.isAcceptableOrUnknown(data['nom_complet']!, _nomCompletMeta),
      );
    } else if (isInserting) {
      context.missing(_nomCompletMeta);
    }
    if (data.containsKey('sexe')) {
      context.handle(
        _sexeMeta,
        sexe.isAcceptableOrUnknown(data['sexe']!, _sexeMeta),
      );
    }
    if (data.containsKey('date_naissance')) {
      context.handle(
        _dateNaissanceMeta,
        dateNaissance.isAcceptableOrUnknown(
          data['date_naissance']!,
          _dateNaissanceMeta,
        ),
      );
    }
    if (data.containsKey('lieu_naissance')) {
      context.handle(
        _lieuNaissanceMeta,
        lieuNaissance.isAcceptableOrUnknown(
          data['lieu_naissance']!,
          _lieuNaissanceMeta,
        ),
      );
    }
    if (data.containsKey('nationalite')) {
      context.handle(
        _nationaliteMeta,
        nationalite.isAcceptableOrUnknown(
          data['nationalite']!,
          _nationaliteMeta,
        ),
      );
    }
    if (data.containsKey('redoublant')) {
      context.handle(
        _redoublantMeta,
        redoublant.isAcceptableOrUnknown(data['redoublant']!, _redoublantMeta),
      );
    }
    if (data.containsKey('statut')) {
      context.handle(
        _statutMeta,
        statut.isAcceptableOrUnknown(data['statut']!, _statutMeta),
      );
    }
    if (data.containsKey('photo_path')) {
      context.handle(
        _photoPathMeta,
        photoPath.isAcceptableOrUnknown(data['photo_path']!, _photoPathMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Eleve map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Eleve(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      classeId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_id'],
      ),
      matricule: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}matricule'],
      ),
      nomComplet: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nom_complet'],
      )!,
      sexe: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}sexe'],
      ),
      dateNaissance: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_naissance'],
      ),
      lieuNaissance: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}lieu_naissance'],
      ),
      nationalite: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nationalite'],
      ),
      redoublant: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}redoublant'],
      )!,
      statut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}statut'],
      ),
      photoPath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}photo_path'],
      ),
    );
  }

  @override
  $ElevesTable createAlias(String alias) {
    return $ElevesTable(attachedDatabase, alias);
  }
}

class Eleve extends DataClass implements Insertable<Eleve> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final int? classeId;
  final String? matricule;
  final String nomComplet;
  final String? sexe;
  final String? dateNaissance;
  final String? lieuNaissance;
  final String? nationalite;
  final bool redoublant;
  final String? statut;
  final String? photoPath;
  const Eleve({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    this.classeId,
    this.matricule,
    required this.nomComplet,
    this.sexe,
    this.dateNaissance,
    this.lieuNaissance,
    this.nationalite,
    required this.redoublant,
    this.statut,
    this.photoPath,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    if (!nullToAbsent || classeId != null) {
      map['classe_id'] = Variable<int>(classeId);
    }
    if (!nullToAbsent || matricule != null) {
      map['matricule'] = Variable<String>(matricule);
    }
    map['nom_complet'] = Variable<String>(nomComplet);
    if (!nullToAbsent || sexe != null) {
      map['sexe'] = Variable<String>(sexe);
    }
    if (!nullToAbsent || dateNaissance != null) {
      map['date_naissance'] = Variable<String>(dateNaissance);
    }
    if (!nullToAbsent || lieuNaissance != null) {
      map['lieu_naissance'] = Variable<String>(lieuNaissance);
    }
    if (!nullToAbsent || nationalite != null) {
      map['nationalite'] = Variable<String>(nationalite);
    }
    map['redoublant'] = Variable<bool>(redoublant);
    if (!nullToAbsent || statut != null) {
      map['statut'] = Variable<String>(statut);
    }
    if (!nullToAbsent || photoPath != null) {
      map['photo_path'] = Variable<String>(photoPath);
    }
    return map;
  }

  ElevesCompanion toCompanion(bool nullToAbsent) {
    return ElevesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      classeId: classeId == null && nullToAbsent
          ? const Value.absent()
          : Value(classeId),
      matricule: matricule == null && nullToAbsent
          ? const Value.absent()
          : Value(matricule),
      nomComplet: Value(nomComplet),
      sexe: sexe == null && nullToAbsent ? const Value.absent() : Value(sexe),
      dateNaissance: dateNaissance == null && nullToAbsent
          ? const Value.absent()
          : Value(dateNaissance),
      lieuNaissance: lieuNaissance == null && nullToAbsent
          ? const Value.absent()
          : Value(lieuNaissance),
      nationalite: nationalite == null && nullToAbsent
          ? const Value.absent()
          : Value(nationalite),
      redoublant: Value(redoublant),
      statut: statut == null && nullToAbsent
          ? const Value.absent()
          : Value(statut),
      photoPath: photoPath == null && nullToAbsent
          ? const Value.absent()
          : Value(photoPath),
    );
  }

  factory Eleve.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Eleve(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      classeId: serializer.fromJson<int?>(json['classeId']),
      matricule: serializer.fromJson<String?>(json['matricule']),
      nomComplet: serializer.fromJson<String>(json['nomComplet']),
      sexe: serializer.fromJson<String?>(json['sexe']),
      dateNaissance: serializer.fromJson<String?>(json['dateNaissance']),
      lieuNaissance: serializer.fromJson<String?>(json['lieuNaissance']),
      nationalite: serializer.fromJson<String?>(json['nationalite']),
      redoublant: serializer.fromJson<bool>(json['redoublant']),
      statut: serializer.fromJson<String?>(json['statut']),
      photoPath: serializer.fromJson<String?>(json['photoPath']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'classeId': serializer.toJson<int?>(classeId),
      'matricule': serializer.toJson<String?>(matricule),
      'nomComplet': serializer.toJson<String>(nomComplet),
      'sexe': serializer.toJson<String?>(sexe),
      'dateNaissance': serializer.toJson<String?>(dateNaissance),
      'lieuNaissance': serializer.toJson<String?>(lieuNaissance),
      'nationalite': serializer.toJson<String?>(nationalite),
      'redoublant': serializer.toJson<bool>(redoublant),
      'statut': serializer.toJson<String?>(statut),
      'photoPath': serializer.toJson<String?>(photoPath),
    };
  }

  Eleve copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    Value<int?> classeId = const Value.absent(),
    Value<String?> matricule = const Value.absent(),
    String? nomComplet,
    Value<String?> sexe = const Value.absent(),
    Value<String?> dateNaissance = const Value.absent(),
    Value<String?> lieuNaissance = const Value.absent(),
    Value<String?> nationalite = const Value.absent(),
    bool? redoublant,
    Value<String?> statut = const Value.absent(),
    Value<String?> photoPath = const Value.absent(),
  }) => Eleve(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    classeId: classeId.present ? classeId.value : this.classeId,
    matricule: matricule.present ? matricule.value : this.matricule,
    nomComplet: nomComplet ?? this.nomComplet,
    sexe: sexe.present ? sexe.value : this.sexe,
    dateNaissance: dateNaissance.present
        ? dateNaissance.value
        : this.dateNaissance,
    lieuNaissance: lieuNaissance.present
        ? lieuNaissance.value
        : this.lieuNaissance,
    nationalite: nationalite.present ? nationalite.value : this.nationalite,
    redoublant: redoublant ?? this.redoublant,
    statut: statut.present ? statut.value : this.statut,
    photoPath: photoPath.present ? photoPath.value : this.photoPath,
  );
  Eleve copyWithCompanion(ElevesCompanion data) {
    return Eleve(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      classeId: data.classeId.present ? data.classeId.value : this.classeId,
      matricule: data.matricule.present ? data.matricule.value : this.matricule,
      nomComplet: data.nomComplet.present
          ? data.nomComplet.value
          : this.nomComplet,
      sexe: data.sexe.present ? data.sexe.value : this.sexe,
      dateNaissance: data.dateNaissance.present
          ? data.dateNaissance.value
          : this.dateNaissance,
      lieuNaissance: data.lieuNaissance.present
          ? data.lieuNaissance.value
          : this.lieuNaissance,
      nationalite: data.nationalite.present
          ? data.nationalite.value
          : this.nationalite,
      redoublant: data.redoublant.present
          ? data.redoublant.value
          : this.redoublant,
      statut: data.statut.present ? data.statut.value : this.statut,
      photoPath: data.photoPath.present ? data.photoPath.value : this.photoPath,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Eleve(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('classeId: $classeId, ')
          ..write('matricule: $matricule, ')
          ..write('nomComplet: $nomComplet, ')
          ..write('sexe: $sexe, ')
          ..write('dateNaissance: $dateNaissance, ')
          ..write('lieuNaissance: $lieuNaissance, ')
          ..write('nationalite: $nationalite, ')
          ..write('redoublant: $redoublant, ')
          ..write('statut: $statut, ')
          ..write('photoPath: $photoPath')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    classeId,
    matricule,
    nomComplet,
    sexe,
    dateNaissance,
    lieuNaissance,
    nationalite,
    redoublant,
    statut,
    photoPath,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Eleve &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.classeId == this.classeId &&
          other.matricule == this.matricule &&
          other.nomComplet == this.nomComplet &&
          other.sexe == this.sexe &&
          other.dateNaissance == this.dateNaissance &&
          other.lieuNaissance == this.lieuNaissance &&
          other.nationalite == this.nationalite &&
          other.redoublant == this.redoublant &&
          other.statut == this.statut &&
          other.photoPath == this.photoPath);
}

class ElevesCompanion extends UpdateCompanion<Eleve> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<int?> classeId;
  final Value<String?> matricule;
  final Value<String> nomComplet;
  final Value<String?> sexe;
  final Value<String?> dateNaissance;
  final Value<String?> lieuNaissance;
  final Value<String?> nationalite;
  final Value<bool> redoublant;
  final Value<String?> statut;
  final Value<String?> photoPath;
  const ElevesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.classeId = const Value.absent(),
    this.matricule = const Value.absent(),
    this.nomComplet = const Value.absent(),
    this.sexe = const Value.absent(),
    this.dateNaissance = const Value.absent(),
    this.lieuNaissance = const Value.absent(),
    this.nationalite = const Value.absent(),
    this.redoublant = const Value.absent(),
    this.statut = const Value.absent(),
    this.photoPath = const Value.absent(),
  });
  ElevesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    this.classeId = const Value.absent(),
    this.matricule = const Value.absent(),
    required String nomComplet,
    this.sexe = const Value.absent(),
    this.dateNaissance = const Value.absent(),
    this.lieuNaissance = const Value.absent(),
    this.nationalite = const Value.absent(),
    this.redoublant = const Value.absent(),
    this.statut = const Value.absent(),
    this.photoPath = const Value.absent(),
  }) : schoolId = Value(schoolId),
       nomComplet = Value(nomComplet);
  static Insertable<Eleve> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<int>? classeId,
    Expression<String>? matricule,
    Expression<String>? nomComplet,
    Expression<String>? sexe,
    Expression<String>? dateNaissance,
    Expression<String>? lieuNaissance,
    Expression<String>? nationalite,
    Expression<bool>? redoublant,
    Expression<String>? statut,
    Expression<String>? photoPath,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (classeId != null) 'classe_id': classeId,
      if (matricule != null) 'matricule': matricule,
      if (nomComplet != null) 'nom_complet': nomComplet,
      if (sexe != null) 'sexe': sexe,
      if (dateNaissance != null) 'date_naissance': dateNaissance,
      if (lieuNaissance != null) 'lieu_naissance': lieuNaissance,
      if (nationalite != null) 'nationalite': nationalite,
      if (redoublant != null) 'redoublant': redoublant,
      if (statut != null) 'statut': statut,
      if (photoPath != null) 'photo_path': photoPath,
    });
  }

  ElevesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<int?>? classeId,
    Value<String?>? matricule,
    Value<String>? nomComplet,
    Value<String?>? sexe,
    Value<String?>? dateNaissance,
    Value<String?>? lieuNaissance,
    Value<String?>? nationalite,
    Value<bool>? redoublant,
    Value<String?>? statut,
    Value<String?>? photoPath,
  }) {
    return ElevesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      classeId: classeId ?? this.classeId,
      matricule: matricule ?? this.matricule,
      nomComplet: nomComplet ?? this.nomComplet,
      sexe: sexe ?? this.sexe,
      dateNaissance: dateNaissance ?? this.dateNaissance,
      lieuNaissance: lieuNaissance ?? this.lieuNaissance,
      nationalite: nationalite ?? this.nationalite,
      redoublant: redoublant ?? this.redoublant,
      statut: statut ?? this.statut,
      photoPath: photoPath ?? this.photoPath,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (classeId.present) {
      map['classe_id'] = Variable<int>(classeId.value);
    }
    if (matricule.present) {
      map['matricule'] = Variable<String>(matricule.value);
    }
    if (nomComplet.present) {
      map['nom_complet'] = Variable<String>(nomComplet.value);
    }
    if (sexe.present) {
      map['sexe'] = Variable<String>(sexe.value);
    }
    if (dateNaissance.present) {
      map['date_naissance'] = Variable<String>(dateNaissance.value);
    }
    if (lieuNaissance.present) {
      map['lieu_naissance'] = Variable<String>(lieuNaissance.value);
    }
    if (nationalite.present) {
      map['nationalite'] = Variable<String>(nationalite.value);
    }
    if (redoublant.present) {
      map['redoublant'] = Variable<bool>(redoublant.value);
    }
    if (statut.present) {
      map['statut'] = Variable<String>(statut.value);
    }
    if (photoPath.present) {
      map['photo_path'] = Variable<String>(photoPath.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ElevesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('classeId: $classeId, ')
          ..write('matricule: $matricule, ')
          ..write('nomComplet: $nomComplet, ')
          ..write('sexe: $sexe, ')
          ..write('dateNaissance: $dateNaissance, ')
          ..write('lieuNaissance: $lieuNaissance, ')
          ..write('nationalite: $nationalite, ')
          ..write('redoublant: $redoublant, ')
          ..write('statut: $statut, ')
          ..write('photoPath: $photoPath')
          ..write(')'))
        .toString();
  }
}

class $PersonnelsTable extends Personnels
    with TableInfo<$PersonnelsTable, Personnel> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PersonnelsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _departementIdMeta = const VerificationMeta(
    'departementId',
  );
  @override
  late final GeneratedColumn<int> departementId = GeneratedColumn<int>(
    'departement_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _fonctionIdMeta = const VerificationMeta(
    'fonctionId',
  );
  @override
  late final GeneratedColumn<int> fonctionId = GeneratedColumn<int>(
    'fonction_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _matriculeMeta = const VerificationMeta(
    'matricule',
  );
  @override
  late final GeneratedColumn<String> matricule = GeneratedColumn<String>(
    'matricule',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _nomCompletMeta = const VerificationMeta(
    'nomComplet',
  );
  @override
  late final GeneratedColumn<String> nomComplet = GeneratedColumn<String>(
    'nom_complet',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _civiliteMeta = const VerificationMeta(
    'civilite',
  );
  @override
  late final GeneratedColumn<String> civilite = GeneratedColumn<String>(
    'civilite',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _sexeMeta = const VerificationMeta('sexe');
  @override
  late final GeneratedColumn<String> sexe = GeneratedColumn<String>(
    'sexe',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _telephoneMeta = const VerificationMeta(
    'telephone',
  );
  @override
  late final GeneratedColumn<String> telephone = GeneratedColumn<String>(
    'telephone',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _emailMeta = const VerificationMeta('email');
  @override
  late final GeneratedColumn<String> email = GeneratedColumn<String>(
    'email',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _statutMeta = const VerificationMeta('statut');
  @override
  late final GeneratedColumn<String> statut = GeneratedColumn<String>(
    'statut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _photoPathMeta = const VerificationMeta(
    'photoPath',
  );
  @override
  late final GeneratedColumn<String> photoPath = GeneratedColumn<String>(
    'photo_path',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    departementId,
    fonctionId,
    matricule,
    nomComplet,
    civilite,
    sexe,
    telephone,
    email,
    statut,
    photoPath,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'personnels';
  @override
  VerificationContext validateIntegrity(
    Insertable<Personnel> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('departement_id')) {
      context.handle(
        _departementIdMeta,
        departementId.isAcceptableOrUnknown(
          data['departement_id']!,
          _departementIdMeta,
        ),
      );
    }
    if (data.containsKey('fonction_id')) {
      context.handle(
        _fonctionIdMeta,
        fonctionId.isAcceptableOrUnknown(data['fonction_id']!, _fonctionIdMeta),
      );
    }
    if (data.containsKey('matricule')) {
      context.handle(
        _matriculeMeta,
        matricule.isAcceptableOrUnknown(data['matricule']!, _matriculeMeta),
      );
    }
    if (data.containsKey('nom_complet')) {
      context.handle(
        _nomCompletMeta,
        nomComplet.isAcceptableOrUnknown(data['nom_complet']!, _nomCompletMeta),
      );
    } else if (isInserting) {
      context.missing(_nomCompletMeta);
    }
    if (data.containsKey('civilite')) {
      context.handle(
        _civiliteMeta,
        civilite.isAcceptableOrUnknown(data['civilite']!, _civiliteMeta),
      );
    }
    if (data.containsKey('sexe')) {
      context.handle(
        _sexeMeta,
        sexe.isAcceptableOrUnknown(data['sexe']!, _sexeMeta),
      );
    }
    if (data.containsKey('telephone')) {
      context.handle(
        _telephoneMeta,
        telephone.isAcceptableOrUnknown(data['telephone']!, _telephoneMeta),
      );
    }
    if (data.containsKey('email')) {
      context.handle(
        _emailMeta,
        email.isAcceptableOrUnknown(data['email']!, _emailMeta),
      );
    }
    if (data.containsKey('statut')) {
      context.handle(
        _statutMeta,
        statut.isAcceptableOrUnknown(data['statut']!, _statutMeta),
      );
    }
    if (data.containsKey('photo_path')) {
      context.handle(
        _photoPathMeta,
        photoPath.isAcceptableOrUnknown(data['photo_path']!, _photoPathMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Personnel map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Personnel(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      departementId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}departement_id'],
      ),
      fonctionId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}fonction_id'],
      ),
      matricule: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}matricule'],
      ),
      nomComplet: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nom_complet'],
      )!,
      civilite: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}civilite'],
      ),
      sexe: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}sexe'],
      ),
      telephone: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}telephone'],
      ),
      email: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}email'],
      ),
      statut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}statut'],
      ),
      photoPath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}photo_path'],
      ),
    );
  }

  @override
  $PersonnelsTable createAlias(String alias) {
    return $PersonnelsTable(attachedDatabase, alias);
  }
}

class Personnel extends DataClass implements Insertable<Personnel> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final int? departementId;
  final int? fonctionId;
  final String? matricule;
  final String nomComplet;
  final String? civilite;
  final String? sexe;
  final String? telephone;
  final String? email;
  final String? statut;
  final String? photoPath;
  const Personnel({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    this.departementId,
    this.fonctionId,
    this.matricule,
    required this.nomComplet,
    this.civilite,
    this.sexe,
    this.telephone,
    this.email,
    this.statut,
    this.photoPath,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    if (!nullToAbsent || departementId != null) {
      map['departement_id'] = Variable<int>(departementId);
    }
    if (!nullToAbsent || fonctionId != null) {
      map['fonction_id'] = Variable<int>(fonctionId);
    }
    if (!nullToAbsent || matricule != null) {
      map['matricule'] = Variable<String>(matricule);
    }
    map['nom_complet'] = Variable<String>(nomComplet);
    if (!nullToAbsent || civilite != null) {
      map['civilite'] = Variable<String>(civilite);
    }
    if (!nullToAbsent || sexe != null) {
      map['sexe'] = Variable<String>(sexe);
    }
    if (!nullToAbsent || telephone != null) {
      map['telephone'] = Variable<String>(telephone);
    }
    if (!nullToAbsent || email != null) {
      map['email'] = Variable<String>(email);
    }
    if (!nullToAbsent || statut != null) {
      map['statut'] = Variable<String>(statut);
    }
    if (!nullToAbsent || photoPath != null) {
      map['photo_path'] = Variable<String>(photoPath);
    }
    return map;
  }

  PersonnelsCompanion toCompanion(bool nullToAbsent) {
    return PersonnelsCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      departementId: departementId == null && nullToAbsent
          ? const Value.absent()
          : Value(departementId),
      fonctionId: fonctionId == null && nullToAbsent
          ? const Value.absent()
          : Value(fonctionId),
      matricule: matricule == null && nullToAbsent
          ? const Value.absent()
          : Value(matricule),
      nomComplet: Value(nomComplet),
      civilite: civilite == null && nullToAbsent
          ? const Value.absent()
          : Value(civilite),
      sexe: sexe == null && nullToAbsent ? const Value.absent() : Value(sexe),
      telephone: telephone == null && nullToAbsent
          ? const Value.absent()
          : Value(telephone),
      email: email == null && nullToAbsent
          ? const Value.absent()
          : Value(email),
      statut: statut == null && nullToAbsent
          ? const Value.absent()
          : Value(statut),
      photoPath: photoPath == null && nullToAbsent
          ? const Value.absent()
          : Value(photoPath),
    );
  }

  factory Personnel.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Personnel(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      departementId: serializer.fromJson<int?>(json['departementId']),
      fonctionId: serializer.fromJson<int?>(json['fonctionId']),
      matricule: serializer.fromJson<String?>(json['matricule']),
      nomComplet: serializer.fromJson<String>(json['nomComplet']),
      civilite: serializer.fromJson<String?>(json['civilite']),
      sexe: serializer.fromJson<String?>(json['sexe']),
      telephone: serializer.fromJson<String?>(json['telephone']),
      email: serializer.fromJson<String?>(json['email']),
      statut: serializer.fromJson<String?>(json['statut']),
      photoPath: serializer.fromJson<String?>(json['photoPath']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'departementId': serializer.toJson<int?>(departementId),
      'fonctionId': serializer.toJson<int?>(fonctionId),
      'matricule': serializer.toJson<String?>(matricule),
      'nomComplet': serializer.toJson<String>(nomComplet),
      'civilite': serializer.toJson<String?>(civilite),
      'sexe': serializer.toJson<String?>(sexe),
      'telephone': serializer.toJson<String?>(telephone),
      'email': serializer.toJson<String?>(email),
      'statut': serializer.toJson<String?>(statut),
      'photoPath': serializer.toJson<String?>(photoPath),
    };
  }

  Personnel copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    Value<int?> departementId = const Value.absent(),
    Value<int?> fonctionId = const Value.absent(),
    Value<String?> matricule = const Value.absent(),
    String? nomComplet,
    Value<String?> civilite = const Value.absent(),
    Value<String?> sexe = const Value.absent(),
    Value<String?> telephone = const Value.absent(),
    Value<String?> email = const Value.absent(),
    Value<String?> statut = const Value.absent(),
    Value<String?> photoPath = const Value.absent(),
  }) => Personnel(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    departementId: departementId.present
        ? departementId.value
        : this.departementId,
    fonctionId: fonctionId.present ? fonctionId.value : this.fonctionId,
    matricule: matricule.present ? matricule.value : this.matricule,
    nomComplet: nomComplet ?? this.nomComplet,
    civilite: civilite.present ? civilite.value : this.civilite,
    sexe: sexe.present ? sexe.value : this.sexe,
    telephone: telephone.present ? telephone.value : this.telephone,
    email: email.present ? email.value : this.email,
    statut: statut.present ? statut.value : this.statut,
    photoPath: photoPath.present ? photoPath.value : this.photoPath,
  );
  Personnel copyWithCompanion(PersonnelsCompanion data) {
    return Personnel(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      departementId: data.departementId.present
          ? data.departementId.value
          : this.departementId,
      fonctionId: data.fonctionId.present
          ? data.fonctionId.value
          : this.fonctionId,
      matricule: data.matricule.present ? data.matricule.value : this.matricule,
      nomComplet: data.nomComplet.present
          ? data.nomComplet.value
          : this.nomComplet,
      civilite: data.civilite.present ? data.civilite.value : this.civilite,
      sexe: data.sexe.present ? data.sexe.value : this.sexe,
      telephone: data.telephone.present ? data.telephone.value : this.telephone,
      email: data.email.present ? data.email.value : this.email,
      statut: data.statut.present ? data.statut.value : this.statut,
      photoPath: data.photoPath.present ? data.photoPath.value : this.photoPath,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Personnel(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('departementId: $departementId, ')
          ..write('fonctionId: $fonctionId, ')
          ..write('matricule: $matricule, ')
          ..write('nomComplet: $nomComplet, ')
          ..write('civilite: $civilite, ')
          ..write('sexe: $sexe, ')
          ..write('telephone: $telephone, ')
          ..write('email: $email, ')
          ..write('statut: $statut, ')
          ..write('photoPath: $photoPath')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    departementId,
    fonctionId,
    matricule,
    nomComplet,
    civilite,
    sexe,
    telephone,
    email,
    statut,
    photoPath,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Personnel &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.departementId == this.departementId &&
          other.fonctionId == this.fonctionId &&
          other.matricule == this.matricule &&
          other.nomComplet == this.nomComplet &&
          other.civilite == this.civilite &&
          other.sexe == this.sexe &&
          other.telephone == this.telephone &&
          other.email == this.email &&
          other.statut == this.statut &&
          other.photoPath == this.photoPath);
}

class PersonnelsCompanion extends UpdateCompanion<Personnel> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<int?> departementId;
  final Value<int?> fonctionId;
  final Value<String?> matricule;
  final Value<String> nomComplet;
  final Value<String?> civilite;
  final Value<String?> sexe;
  final Value<String?> telephone;
  final Value<String?> email;
  final Value<String?> statut;
  final Value<String?> photoPath;
  const PersonnelsCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.departementId = const Value.absent(),
    this.fonctionId = const Value.absent(),
    this.matricule = const Value.absent(),
    this.nomComplet = const Value.absent(),
    this.civilite = const Value.absent(),
    this.sexe = const Value.absent(),
    this.telephone = const Value.absent(),
    this.email = const Value.absent(),
    this.statut = const Value.absent(),
    this.photoPath = const Value.absent(),
  });
  PersonnelsCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    this.departementId = const Value.absent(),
    this.fonctionId = const Value.absent(),
    this.matricule = const Value.absent(),
    required String nomComplet,
    this.civilite = const Value.absent(),
    this.sexe = const Value.absent(),
    this.telephone = const Value.absent(),
    this.email = const Value.absent(),
    this.statut = const Value.absent(),
    this.photoPath = const Value.absent(),
  }) : schoolId = Value(schoolId),
       nomComplet = Value(nomComplet);
  static Insertable<Personnel> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<int>? departementId,
    Expression<int>? fonctionId,
    Expression<String>? matricule,
    Expression<String>? nomComplet,
    Expression<String>? civilite,
    Expression<String>? sexe,
    Expression<String>? telephone,
    Expression<String>? email,
    Expression<String>? statut,
    Expression<String>? photoPath,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (departementId != null) 'departement_id': departementId,
      if (fonctionId != null) 'fonction_id': fonctionId,
      if (matricule != null) 'matricule': matricule,
      if (nomComplet != null) 'nom_complet': nomComplet,
      if (civilite != null) 'civilite': civilite,
      if (sexe != null) 'sexe': sexe,
      if (telephone != null) 'telephone': telephone,
      if (email != null) 'email': email,
      if (statut != null) 'statut': statut,
      if (photoPath != null) 'photo_path': photoPath,
    });
  }

  PersonnelsCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<int?>? departementId,
    Value<int?>? fonctionId,
    Value<String?>? matricule,
    Value<String>? nomComplet,
    Value<String?>? civilite,
    Value<String?>? sexe,
    Value<String?>? telephone,
    Value<String?>? email,
    Value<String?>? statut,
    Value<String?>? photoPath,
  }) {
    return PersonnelsCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      departementId: departementId ?? this.departementId,
      fonctionId: fonctionId ?? this.fonctionId,
      matricule: matricule ?? this.matricule,
      nomComplet: nomComplet ?? this.nomComplet,
      civilite: civilite ?? this.civilite,
      sexe: sexe ?? this.sexe,
      telephone: telephone ?? this.telephone,
      email: email ?? this.email,
      statut: statut ?? this.statut,
      photoPath: photoPath ?? this.photoPath,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (departementId.present) {
      map['departement_id'] = Variable<int>(departementId.value);
    }
    if (fonctionId.present) {
      map['fonction_id'] = Variable<int>(fonctionId.value);
    }
    if (matricule.present) {
      map['matricule'] = Variable<String>(matricule.value);
    }
    if (nomComplet.present) {
      map['nom_complet'] = Variable<String>(nomComplet.value);
    }
    if (civilite.present) {
      map['civilite'] = Variable<String>(civilite.value);
    }
    if (sexe.present) {
      map['sexe'] = Variable<String>(sexe.value);
    }
    if (telephone.present) {
      map['telephone'] = Variable<String>(telephone.value);
    }
    if (email.present) {
      map['email'] = Variable<String>(email.value);
    }
    if (statut.present) {
      map['statut'] = Variable<String>(statut.value);
    }
    if (photoPath.present) {
      map['photo_path'] = Variable<String>(photoPath.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PersonnelsCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('departementId: $departementId, ')
          ..write('fonctionId: $fonctionId, ')
          ..write('matricule: $matricule, ')
          ..write('nomComplet: $nomComplet, ')
          ..write('civilite: $civilite, ')
          ..write('sexe: $sexe, ')
          ..write('telephone: $telephone, ')
          ..write('email: $email, ')
          ..write('statut: $statut, ')
          ..write('photoPath: $photoPath')
          ..write(')'))
        .toString();
  }
}

class $SeancesTable extends Seances with TableInfo<$SeancesTable, Seance> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SeancesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _classeIdMeta = const VerificationMeta(
    'classeId',
  );
  @override
  late final GeneratedColumn<int> classeId = GeneratedColumn<int>(
    'classe_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _classeMatiereIdMeta = const VerificationMeta(
    'classeMatiereId',
  );
  @override
  late final GeneratedColumn<int> classeMatiereId = GeneratedColumn<int>(
    'classe_matiere_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _trimestreIdMeta = const VerificationMeta(
    'trimestreId',
  );
  @override
  late final GeneratedColumn<int> trimestreId = GeneratedColumn<int>(
    'trimestre_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _emploiDuTempsIdMeta = const VerificationMeta(
    'emploiDuTempsId',
  );
  @override
  late final GeneratedColumn<int> emploiDuTempsId = GeneratedColumn<int>(
    'emploi_du_temps_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dateSeanceMeta = const VerificationMeta(
    'dateSeance',
  );
  @override
  late final GeneratedColumn<String> dateSeance = GeneratedColumn<String>(
    'date_seance',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _heureDebutMeta = const VerificationMeta(
    'heureDebut',
  );
  @override
  late final GeneratedColumn<String> heureDebut = GeneratedColumn<String>(
    'heure_debut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _heureFinMeta = const VerificationMeta(
    'heureFin',
  );
  @override
  late final GeneratedColumn<String> heureFin = GeneratedColumn<String>(
    'heure_fin',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _salleMeta = const VerificationMeta('salle');
  @override
  late final GeneratedColumn<String> salle = GeneratedColumn<String>(
    'salle',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _contenuMeta = const VerificationMeta(
    'contenu',
  );
  @override
  late final GeneratedColumn<String> contenu = GeneratedColumn<String>(
    'contenu',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _observationsMeta = const VerificationMeta(
    'observations',
  );
  @override
  late final GeneratedColumn<String> observations = GeneratedColumn<String>(
    'observations',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _donneesPersonnaliseesMeta =
      const VerificationMeta('donneesPersonnalisees');
  @override
  late final GeneratedColumn<String> donneesPersonnalisees =
      GeneratedColumn<String>(
        'donnees_personnalisees',
        aliasedName,
        true,
        type: DriftSqlType.string,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _statutMeta = const VerificationMeta('statut');
  @override
  late final GeneratedColumn<String> statut = GeneratedColumn<String>(
    'statut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    classeId,
    classeMatiereId,
    trimestreId,
    emploiDuTempsId,
    dateSeance,
    heureDebut,
    heureFin,
    salle,
    contenu,
    observations,
    donneesPersonnalisees,
    statut,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'seances';
  @override
  VerificationContext validateIntegrity(
    Insertable<Seance> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('classe_id')) {
      context.handle(
        _classeIdMeta,
        classeId.isAcceptableOrUnknown(data['classe_id']!, _classeIdMeta),
      );
    } else if (isInserting) {
      context.missing(_classeIdMeta);
    }
    if (data.containsKey('classe_matiere_id')) {
      context.handle(
        _classeMatiereIdMeta,
        classeMatiereId.isAcceptableOrUnknown(
          data['classe_matiere_id']!,
          _classeMatiereIdMeta,
        ),
      );
    }
    if (data.containsKey('trimestre_id')) {
      context.handle(
        _trimestreIdMeta,
        trimestreId.isAcceptableOrUnknown(
          data['trimestre_id']!,
          _trimestreIdMeta,
        ),
      );
    }
    if (data.containsKey('emploi_du_temps_id')) {
      context.handle(
        _emploiDuTempsIdMeta,
        emploiDuTempsId.isAcceptableOrUnknown(
          data['emploi_du_temps_id']!,
          _emploiDuTempsIdMeta,
        ),
      );
    }
    if (data.containsKey('date_seance')) {
      context.handle(
        _dateSeanceMeta,
        dateSeance.isAcceptableOrUnknown(data['date_seance']!, _dateSeanceMeta),
      );
    }
    if (data.containsKey('heure_debut')) {
      context.handle(
        _heureDebutMeta,
        heureDebut.isAcceptableOrUnknown(data['heure_debut']!, _heureDebutMeta),
      );
    }
    if (data.containsKey('heure_fin')) {
      context.handle(
        _heureFinMeta,
        heureFin.isAcceptableOrUnknown(data['heure_fin']!, _heureFinMeta),
      );
    }
    if (data.containsKey('salle')) {
      context.handle(
        _salleMeta,
        salle.isAcceptableOrUnknown(data['salle']!, _salleMeta),
      );
    }
    if (data.containsKey('contenu')) {
      context.handle(
        _contenuMeta,
        contenu.isAcceptableOrUnknown(data['contenu']!, _contenuMeta),
      );
    }
    if (data.containsKey('observations')) {
      context.handle(
        _observationsMeta,
        observations.isAcceptableOrUnknown(
          data['observations']!,
          _observationsMeta,
        ),
      );
    }
    if (data.containsKey('donnees_personnalisees')) {
      context.handle(
        _donneesPersonnaliseesMeta,
        donneesPersonnalisees.isAcceptableOrUnknown(
          data['donnees_personnalisees']!,
          _donneesPersonnaliseesMeta,
        ),
      );
    }
    if (data.containsKey('statut')) {
      context.handle(
        _statutMeta,
        statut.isAcceptableOrUnknown(data['statut']!, _statutMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Seance map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Seance(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      classeId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_id'],
      )!,
      classeMatiereId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_matiere_id'],
      ),
      trimestreId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}trimestre_id'],
      ),
      emploiDuTempsId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}emploi_du_temps_id'],
      ),
      dateSeance: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_seance'],
      ),
      heureDebut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}heure_debut'],
      ),
      heureFin: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}heure_fin'],
      ),
      salle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}salle'],
      ),
      contenu: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}contenu'],
      ),
      observations: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}observations'],
      ),
      donneesPersonnalisees: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}donnees_personnalisees'],
      ),
      statut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}statut'],
      ),
    );
  }

  @override
  $SeancesTable createAlias(String alias) {
    return $SeancesTable(attachedDatabase, alias);
  }
}

class Seance extends DataClass implements Insertable<Seance> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final int classeId;
  final int? classeMatiereId;
  final int? trimestreId;
  final int? emploiDuTempsId;
  final String? dateSeance;
  final String? heureDebut;
  final String? heureFin;
  final String? salle;
  final String? contenu;
  final String? observations;
  final String? donneesPersonnalisees;
  final String? statut;
  const Seance({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    required this.classeId,
    this.classeMatiereId,
    this.trimestreId,
    this.emploiDuTempsId,
    this.dateSeance,
    this.heureDebut,
    this.heureFin,
    this.salle,
    this.contenu,
    this.observations,
    this.donneesPersonnalisees,
    this.statut,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    map['classe_id'] = Variable<int>(classeId);
    if (!nullToAbsent || classeMatiereId != null) {
      map['classe_matiere_id'] = Variable<int>(classeMatiereId);
    }
    if (!nullToAbsent || trimestreId != null) {
      map['trimestre_id'] = Variable<int>(trimestreId);
    }
    if (!nullToAbsent || emploiDuTempsId != null) {
      map['emploi_du_temps_id'] = Variable<int>(emploiDuTempsId);
    }
    if (!nullToAbsent || dateSeance != null) {
      map['date_seance'] = Variable<String>(dateSeance);
    }
    if (!nullToAbsent || heureDebut != null) {
      map['heure_debut'] = Variable<String>(heureDebut);
    }
    if (!nullToAbsent || heureFin != null) {
      map['heure_fin'] = Variable<String>(heureFin);
    }
    if (!nullToAbsent || salle != null) {
      map['salle'] = Variable<String>(salle);
    }
    if (!nullToAbsent || contenu != null) {
      map['contenu'] = Variable<String>(contenu);
    }
    if (!nullToAbsent || observations != null) {
      map['observations'] = Variable<String>(observations);
    }
    if (!nullToAbsent || donneesPersonnalisees != null) {
      map['donnees_personnalisees'] = Variable<String>(donneesPersonnalisees);
    }
    if (!nullToAbsent || statut != null) {
      map['statut'] = Variable<String>(statut);
    }
    return map;
  }

  SeancesCompanion toCompanion(bool nullToAbsent) {
    return SeancesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      classeId: Value(classeId),
      classeMatiereId: classeMatiereId == null && nullToAbsent
          ? const Value.absent()
          : Value(classeMatiereId),
      trimestreId: trimestreId == null && nullToAbsent
          ? const Value.absent()
          : Value(trimestreId),
      emploiDuTempsId: emploiDuTempsId == null && nullToAbsent
          ? const Value.absent()
          : Value(emploiDuTempsId),
      dateSeance: dateSeance == null && nullToAbsent
          ? const Value.absent()
          : Value(dateSeance),
      heureDebut: heureDebut == null && nullToAbsent
          ? const Value.absent()
          : Value(heureDebut),
      heureFin: heureFin == null && nullToAbsent
          ? const Value.absent()
          : Value(heureFin),
      salle: salle == null && nullToAbsent
          ? const Value.absent()
          : Value(salle),
      contenu: contenu == null && nullToAbsent
          ? const Value.absent()
          : Value(contenu),
      observations: observations == null && nullToAbsent
          ? const Value.absent()
          : Value(observations),
      donneesPersonnalisees: donneesPersonnalisees == null && nullToAbsent
          ? const Value.absent()
          : Value(donneesPersonnalisees),
      statut: statut == null && nullToAbsent
          ? const Value.absent()
          : Value(statut),
    );
  }

  factory Seance.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Seance(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      classeId: serializer.fromJson<int>(json['classeId']),
      classeMatiereId: serializer.fromJson<int?>(json['classeMatiereId']),
      trimestreId: serializer.fromJson<int?>(json['trimestreId']),
      emploiDuTempsId: serializer.fromJson<int?>(json['emploiDuTempsId']),
      dateSeance: serializer.fromJson<String?>(json['dateSeance']),
      heureDebut: serializer.fromJson<String?>(json['heureDebut']),
      heureFin: serializer.fromJson<String?>(json['heureFin']),
      salle: serializer.fromJson<String?>(json['salle']),
      contenu: serializer.fromJson<String?>(json['contenu']),
      observations: serializer.fromJson<String?>(json['observations']),
      donneesPersonnalisees: serializer.fromJson<String?>(
        json['donneesPersonnalisees'],
      ),
      statut: serializer.fromJson<String?>(json['statut']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'classeId': serializer.toJson<int>(classeId),
      'classeMatiereId': serializer.toJson<int?>(classeMatiereId),
      'trimestreId': serializer.toJson<int?>(trimestreId),
      'emploiDuTempsId': serializer.toJson<int?>(emploiDuTempsId),
      'dateSeance': serializer.toJson<String?>(dateSeance),
      'heureDebut': serializer.toJson<String?>(heureDebut),
      'heureFin': serializer.toJson<String?>(heureFin),
      'salle': serializer.toJson<String?>(salle),
      'contenu': serializer.toJson<String?>(contenu),
      'observations': serializer.toJson<String?>(observations),
      'donneesPersonnalisees': serializer.toJson<String?>(
        donneesPersonnalisees,
      ),
      'statut': serializer.toJson<String?>(statut),
    };
  }

  Seance copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    int? classeId,
    Value<int?> classeMatiereId = const Value.absent(),
    Value<int?> trimestreId = const Value.absent(),
    Value<int?> emploiDuTempsId = const Value.absent(),
    Value<String?> dateSeance = const Value.absent(),
    Value<String?> heureDebut = const Value.absent(),
    Value<String?> heureFin = const Value.absent(),
    Value<String?> salle = const Value.absent(),
    Value<String?> contenu = const Value.absent(),
    Value<String?> observations = const Value.absent(),
    Value<String?> donneesPersonnalisees = const Value.absent(),
    Value<String?> statut = const Value.absent(),
  }) => Seance(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    classeId: classeId ?? this.classeId,
    classeMatiereId: classeMatiereId.present
        ? classeMatiereId.value
        : this.classeMatiereId,
    trimestreId: trimestreId.present ? trimestreId.value : this.trimestreId,
    emploiDuTempsId: emploiDuTempsId.present
        ? emploiDuTempsId.value
        : this.emploiDuTempsId,
    dateSeance: dateSeance.present ? dateSeance.value : this.dateSeance,
    heureDebut: heureDebut.present ? heureDebut.value : this.heureDebut,
    heureFin: heureFin.present ? heureFin.value : this.heureFin,
    salle: salle.present ? salle.value : this.salle,
    contenu: contenu.present ? contenu.value : this.contenu,
    observations: observations.present ? observations.value : this.observations,
    donneesPersonnalisees: donneesPersonnalisees.present
        ? donneesPersonnalisees.value
        : this.donneesPersonnalisees,
    statut: statut.present ? statut.value : this.statut,
  );
  Seance copyWithCompanion(SeancesCompanion data) {
    return Seance(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      classeId: data.classeId.present ? data.classeId.value : this.classeId,
      classeMatiereId: data.classeMatiereId.present
          ? data.classeMatiereId.value
          : this.classeMatiereId,
      trimestreId: data.trimestreId.present
          ? data.trimestreId.value
          : this.trimestreId,
      emploiDuTempsId: data.emploiDuTempsId.present
          ? data.emploiDuTempsId.value
          : this.emploiDuTempsId,
      dateSeance: data.dateSeance.present
          ? data.dateSeance.value
          : this.dateSeance,
      heureDebut: data.heureDebut.present
          ? data.heureDebut.value
          : this.heureDebut,
      heureFin: data.heureFin.present ? data.heureFin.value : this.heureFin,
      salle: data.salle.present ? data.salle.value : this.salle,
      contenu: data.contenu.present ? data.contenu.value : this.contenu,
      observations: data.observations.present
          ? data.observations.value
          : this.observations,
      donneesPersonnalisees: data.donneesPersonnalisees.present
          ? data.donneesPersonnalisees.value
          : this.donneesPersonnalisees,
      statut: data.statut.present ? data.statut.value : this.statut,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Seance(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('classeId: $classeId, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('trimestreId: $trimestreId, ')
          ..write('emploiDuTempsId: $emploiDuTempsId, ')
          ..write('dateSeance: $dateSeance, ')
          ..write('heureDebut: $heureDebut, ')
          ..write('heureFin: $heureFin, ')
          ..write('salle: $salle, ')
          ..write('contenu: $contenu, ')
          ..write('observations: $observations, ')
          ..write('donneesPersonnalisees: $donneesPersonnalisees, ')
          ..write('statut: $statut')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    classeId,
    classeMatiereId,
    trimestreId,
    emploiDuTempsId,
    dateSeance,
    heureDebut,
    heureFin,
    salle,
    contenu,
    observations,
    donneesPersonnalisees,
    statut,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Seance &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.classeId == this.classeId &&
          other.classeMatiereId == this.classeMatiereId &&
          other.trimestreId == this.trimestreId &&
          other.emploiDuTempsId == this.emploiDuTempsId &&
          other.dateSeance == this.dateSeance &&
          other.heureDebut == this.heureDebut &&
          other.heureFin == this.heureFin &&
          other.salle == this.salle &&
          other.contenu == this.contenu &&
          other.observations == this.observations &&
          other.donneesPersonnalisees == this.donneesPersonnalisees &&
          other.statut == this.statut);
}

class SeancesCompanion extends UpdateCompanion<Seance> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<int> classeId;
  final Value<int?> classeMatiereId;
  final Value<int?> trimestreId;
  final Value<int?> emploiDuTempsId;
  final Value<String?> dateSeance;
  final Value<String?> heureDebut;
  final Value<String?> heureFin;
  final Value<String?> salle;
  final Value<String?> contenu;
  final Value<String?> observations;
  final Value<String?> donneesPersonnalisees;
  final Value<String?> statut;
  const SeancesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.classeId = const Value.absent(),
    this.classeMatiereId = const Value.absent(),
    this.trimestreId = const Value.absent(),
    this.emploiDuTempsId = const Value.absent(),
    this.dateSeance = const Value.absent(),
    this.heureDebut = const Value.absent(),
    this.heureFin = const Value.absent(),
    this.salle = const Value.absent(),
    this.contenu = const Value.absent(),
    this.observations = const Value.absent(),
    this.donneesPersonnalisees = const Value.absent(),
    this.statut = const Value.absent(),
  });
  SeancesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    required int classeId,
    this.classeMatiereId = const Value.absent(),
    this.trimestreId = const Value.absent(),
    this.emploiDuTempsId = const Value.absent(),
    this.dateSeance = const Value.absent(),
    this.heureDebut = const Value.absent(),
    this.heureFin = const Value.absent(),
    this.salle = const Value.absent(),
    this.contenu = const Value.absent(),
    this.observations = const Value.absent(),
    this.donneesPersonnalisees = const Value.absent(),
    this.statut = const Value.absent(),
  }) : schoolId = Value(schoolId),
       classeId = Value(classeId);
  static Insertable<Seance> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<int>? classeId,
    Expression<int>? classeMatiereId,
    Expression<int>? trimestreId,
    Expression<int>? emploiDuTempsId,
    Expression<String>? dateSeance,
    Expression<String>? heureDebut,
    Expression<String>? heureFin,
    Expression<String>? salle,
    Expression<String>? contenu,
    Expression<String>? observations,
    Expression<String>? donneesPersonnalisees,
    Expression<String>? statut,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (classeId != null) 'classe_id': classeId,
      if (classeMatiereId != null) 'classe_matiere_id': classeMatiereId,
      if (trimestreId != null) 'trimestre_id': trimestreId,
      if (emploiDuTempsId != null) 'emploi_du_temps_id': emploiDuTempsId,
      if (dateSeance != null) 'date_seance': dateSeance,
      if (heureDebut != null) 'heure_debut': heureDebut,
      if (heureFin != null) 'heure_fin': heureFin,
      if (salle != null) 'salle': salle,
      if (contenu != null) 'contenu': contenu,
      if (observations != null) 'observations': observations,
      if (donneesPersonnalisees != null)
        'donnees_personnalisees': donneesPersonnalisees,
      if (statut != null) 'statut': statut,
    });
  }

  SeancesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<int>? classeId,
    Value<int?>? classeMatiereId,
    Value<int?>? trimestreId,
    Value<int?>? emploiDuTempsId,
    Value<String?>? dateSeance,
    Value<String?>? heureDebut,
    Value<String?>? heureFin,
    Value<String?>? salle,
    Value<String?>? contenu,
    Value<String?>? observations,
    Value<String?>? donneesPersonnalisees,
    Value<String?>? statut,
  }) {
    return SeancesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      classeId: classeId ?? this.classeId,
      classeMatiereId: classeMatiereId ?? this.classeMatiereId,
      trimestreId: trimestreId ?? this.trimestreId,
      emploiDuTempsId: emploiDuTempsId ?? this.emploiDuTempsId,
      dateSeance: dateSeance ?? this.dateSeance,
      heureDebut: heureDebut ?? this.heureDebut,
      heureFin: heureFin ?? this.heureFin,
      salle: salle ?? this.salle,
      contenu: contenu ?? this.contenu,
      observations: observations ?? this.observations,
      donneesPersonnalisees:
          donneesPersonnalisees ?? this.donneesPersonnalisees,
      statut: statut ?? this.statut,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (classeId.present) {
      map['classe_id'] = Variable<int>(classeId.value);
    }
    if (classeMatiereId.present) {
      map['classe_matiere_id'] = Variable<int>(classeMatiereId.value);
    }
    if (trimestreId.present) {
      map['trimestre_id'] = Variable<int>(trimestreId.value);
    }
    if (emploiDuTempsId.present) {
      map['emploi_du_temps_id'] = Variable<int>(emploiDuTempsId.value);
    }
    if (dateSeance.present) {
      map['date_seance'] = Variable<String>(dateSeance.value);
    }
    if (heureDebut.present) {
      map['heure_debut'] = Variable<String>(heureDebut.value);
    }
    if (heureFin.present) {
      map['heure_fin'] = Variable<String>(heureFin.value);
    }
    if (salle.present) {
      map['salle'] = Variable<String>(salle.value);
    }
    if (contenu.present) {
      map['contenu'] = Variable<String>(contenu.value);
    }
    if (observations.present) {
      map['observations'] = Variable<String>(observations.value);
    }
    if (donneesPersonnalisees.present) {
      map['donnees_personnalisees'] = Variable<String>(
        donneesPersonnalisees.value,
      );
    }
    if (statut.present) {
      map['statut'] = Variable<String>(statut.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SeancesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('classeId: $classeId, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('trimestreId: $trimestreId, ')
          ..write('emploiDuTempsId: $emploiDuTempsId, ')
          ..write('dateSeance: $dateSeance, ')
          ..write('heureDebut: $heureDebut, ')
          ..write('heureFin: $heureFin, ')
          ..write('salle: $salle, ')
          ..write('contenu: $contenu, ')
          ..write('observations: $observations, ')
          ..write('donneesPersonnalisees: $donneesPersonnalisees, ')
          ..write('statut: $statut')
          ..write(')'))
        .toString();
  }
}

class $PresencesTable extends Presences
    with TableInfo<$PresencesTable, Presence> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PresencesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _seanceIdMeta = const VerificationMeta(
    'seanceId',
  );
  @override
  late final GeneratedColumn<int> seanceId = GeneratedColumn<int>(
    'seance_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _eleveIdMeta = const VerificationMeta(
    'eleveId',
  );
  @override
  late final GeneratedColumn<int> eleveId = GeneratedColumn<int>(
    'eleve_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _statutMeta = const VerificationMeta('statut');
  @override
  late final GeneratedColumn<String> statut = GeneratedColumn<String>(
    'statut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _motifMeta = const VerificationMeta('motif');
  @override
  late final GeneratedColumn<String> motif = GeneratedColumn<String>(
    'motif',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _justifieMeta = const VerificationMeta(
    'justifie',
  );
  @override
  late final GeneratedColumn<bool> justifie = GeneratedColumn<bool>(
    'justifie',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("justifie" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _remarqueMeta = const VerificationMeta(
    'remarque',
  );
  @override
  late final GeneratedColumn<String> remarque = GeneratedColumn<String>(
    'remarque',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    seanceId,
    eleveId,
    statut,
    motif,
    justifie,
    remarque,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'presences';
  @override
  VerificationContext validateIntegrity(
    Insertable<Presence> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('seance_id')) {
      context.handle(
        _seanceIdMeta,
        seanceId.isAcceptableOrUnknown(data['seance_id']!, _seanceIdMeta),
      );
    } else if (isInserting) {
      context.missing(_seanceIdMeta);
    }
    if (data.containsKey('eleve_id')) {
      context.handle(
        _eleveIdMeta,
        eleveId.isAcceptableOrUnknown(data['eleve_id']!, _eleveIdMeta),
      );
    } else if (isInserting) {
      context.missing(_eleveIdMeta);
    }
    if (data.containsKey('statut')) {
      context.handle(
        _statutMeta,
        statut.isAcceptableOrUnknown(data['statut']!, _statutMeta),
      );
    }
    if (data.containsKey('motif')) {
      context.handle(
        _motifMeta,
        motif.isAcceptableOrUnknown(data['motif']!, _motifMeta),
      );
    }
    if (data.containsKey('justifie')) {
      context.handle(
        _justifieMeta,
        justifie.isAcceptableOrUnknown(data['justifie']!, _justifieMeta),
      );
    }
    if (data.containsKey('remarque')) {
      context.handle(
        _remarqueMeta,
        remarque.isAcceptableOrUnknown(data['remarque']!, _remarqueMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Presence map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Presence(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      seanceId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}seance_id'],
      )!,
      eleveId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}eleve_id'],
      )!,
      statut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}statut'],
      ),
      motif: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}motif'],
      ),
      justifie: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}justifie'],
      )!,
      remarque: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}remarque'],
      ),
    );
  }

  @override
  $PresencesTable createAlias(String alias) {
    return $PresencesTable(attachedDatabase, alias);
  }
}

class Presence extends DataClass implements Insertable<Presence> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int seanceId;
  final int eleveId;
  final String? statut;
  final String? motif;
  final bool justifie;
  final String? remarque;
  const Presence({
    required this.id,
    required this.etatSync,
    required this.seanceId,
    required this.eleveId,
    this.statut,
    this.motif,
    required this.justifie,
    this.remarque,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['seance_id'] = Variable<int>(seanceId);
    map['eleve_id'] = Variable<int>(eleveId);
    if (!nullToAbsent || statut != null) {
      map['statut'] = Variable<String>(statut);
    }
    if (!nullToAbsent || motif != null) {
      map['motif'] = Variable<String>(motif);
    }
    map['justifie'] = Variable<bool>(justifie);
    if (!nullToAbsent || remarque != null) {
      map['remarque'] = Variable<String>(remarque);
    }
    return map;
  }

  PresencesCompanion toCompanion(bool nullToAbsent) {
    return PresencesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      seanceId: Value(seanceId),
      eleveId: Value(eleveId),
      statut: statut == null && nullToAbsent
          ? const Value.absent()
          : Value(statut),
      motif: motif == null && nullToAbsent
          ? const Value.absent()
          : Value(motif),
      justifie: Value(justifie),
      remarque: remarque == null && nullToAbsent
          ? const Value.absent()
          : Value(remarque),
    );
  }

  factory Presence.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Presence(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      seanceId: serializer.fromJson<int>(json['seanceId']),
      eleveId: serializer.fromJson<int>(json['eleveId']),
      statut: serializer.fromJson<String?>(json['statut']),
      motif: serializer.fromJson<String?>(json['motif']),
      justifie: serializer.fromJson<bool>(json['justifie']),
      remarque: serializer.fromJson<String?>(json['remarque']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'seanceId': serializer.toJson<int>(seanceId),
      'eleveId': serializer.toJson<int>(eleveId),
      'statut': serializer.toJson<String?>(statut),
      'motif': serializer.toJson<String?>(motif),
      'justifie': serializer.toJson<bool>(justifie),
      'remarque': serializer.toJson<String?>(remarque),
    };
  }

  Presence copyWith({
    int? id,
    String? etatSync,
    int? seanceId,
    int? eleveId,
    Value<String?> statut = const Value.absent(),
    Value<String?> motif = const Value.absent(),
    bool? justifie,
    Value<String?> remarque = const Value.absent(),
  }) => Presence(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    seanceId: seanceId ?? this.seanceId,
    eleveId: eleveId ?? this.eleveId,
    statut: statut.present ? statut.value : this.statut,
    motif: motif.present ? motif.value : this.motif,
    justifie: justifie ?? this.justifie,
    remarque: remarque.present ? remarque.value : this.remarque,
  );
  Presence copyWithCompanion(PresencesCompanion data) {
    return Presence(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      seanceId: data.seanceId.present ? data.seanceId.value : this.seanceId,
      eleveId: data.eleveId.present ? data.eleveId.value : this.eleveId,
      statut: data.statut.present ? data.statut.value : this.statut,
      motif: data.motif.present ? data.motif.value : this.motif,
      justifie: data.justifie.present ? data.justifie.value : this.justifie,
      remarque: data.remarque.present ? data.remarque.value : this.remarque,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Presence(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('seanceId: $seanceId, ')
          ..write('eleveId: $eleveId, ')
          ..write('statut: $statut, ')
          ..write('motif: $motif, ')
          ..write('justifie: $justifie, ')
          ..write('remarque: $remarque')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    seanceId,
    eleveId,
    statut,
    motif,
    justifie,
    remarque,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Presence &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.seanceId == this.seanceId &&
          other.eleveId == this.eleveId &&
          other.statut == this.statut &&
          other.motif == this.motif &&
          other.justifie == this.justifie &&
          other.remarque == this.remarque);
}

class PresencesCompanion extends UpdateCompanion<Presence> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> seanceId;
  final Value<int> eleveId;
  final Value<String?> statut;
  final Value<String?> motif;
  final Value<bool> justifie;
  final Value<String?> remarque;
  const PresencesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.seanceId = const Value.absent(),
    this.eleveId = const Value.absent(),
    this.statut = const Value.absent(),
    this.motif = const Value.absent(),
    this.justifie = const Value.absent(),
    this.remarque = const Value.absent(),
  });
  PresencesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int seanceId,
    required int eleveId,
    this.statut = const Value.absent(),
    this.motif = const Value.absent(),
    this.justifie = const Value.absent(),
    this.remarque = const Value.absent(),
  }) : seanceId = Value(seanceId),
       eleveId = Value(eleveId);
  static Insertable<Presence> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? seanceId,
    Expression<int>? eleveId,
    Expression<String>? statut,
    Expression<String>? motif,
    Expression<bool>? justifie,
    Expression<String>? remarque,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (seanceId != null) 'seance_id': seanceId,
      if (eleveId != null) 'eleve_id': eleveId,
      if (statut != null) 'statut': statut,
      if (motif != null) 'motif': motif,
      if (justifie != null) 'justifie': justifie,
      if (remarque != null) 'remarque': remarque,
    });
  }

  PresencesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? seanceId,
    Value<int>? eleveId,
    Value<String?>? statut,
    Value<String?>? motif,
    Value<bool>? justifie,
    Value<String?>? remarque,
  }) {
    return PresencesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      seanceId: seanceId ?? this.seanceId,
      eleveId: eleveId ?? this.eleveId,
      statut: statut ?? this.statut,
      motif: motif ?? this.motif,
      justifie: justifie ?? this.justifie,
      remarque: remarque ?? this.remarque,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (seanceId.present) {
      map['seance_id'] = Variable<int>(seanceId.value);
    }
    if (eleveId.present) {
      map['eleve_id'] = Variable<int>(eleveId.value);
    }
    if (statut.present) {
      map['statut'] = Variable<String>(statut.value);
    }
    if (motif.present) {
      map['motif'] = Variable<String>(motif.value);
    }
    if (justifie.present) {
      map['justifie'] = Variable<bool>(justifie.value);
    }
    if (remarque.present) {
      map['remarque'] = Variable<String>(remarque.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PresencesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('seanceId: $seanceId, ')
          ..write('eleveId: $eleveId, ')
          ..write('statut: $statut, ')
          ..write('motif: $motif, ')
          ..write('justifie: $justifie, ')
          ..write('remarque: $remarque')
          ..write(')'))
        .toString();
  }
}

class $NotesTable extends Notes with TableInfo<$NotesTable, Note> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $NotesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _eleveIdMeta = const VerificationMeta(
    'eleveId',
  );
  @override
  late final GeneratedColumn<int> eleveId = GeneratedColumn<int>(
    'eleve_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _classeMatiereIdMeta = const VerificationMeta(
    'classeMatiereId',
  );
  @override
  late final GeneratedColumn<int> classeMatiereId = GeneratedColumn<int>(
    'classe_matiere_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _sequenceIdMeta = const VerificationMeta(
    'sequenceId',
  );
  @override
  late final GeneratedColumn<int> sequenceId = GeneratedColumn<int>(
    'sequence_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _composanteMeta = const VerificationMeta(
    'composante',
  );
  @override
  late final GeneratedColumn<String> composante = GeneratedColumn<String>(
    'composante',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _valeurMeta = const VerificationMeta('valeur');
  @override
  late final GeneratedColumn<double> valeur = GeneratedColumn<double>(
    'valeur',
    aliasedName,
    true,
    type: DriftSqlType.double,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _saisiParMeta = const VerificationMeta(
    'saisiPar',
  );
  @override
  late final GeneratedColumn<int> saisiPar = GeneratedColumn<int>(
    'saisi_par',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    eleveId,
    classeMatiereId,
    sequenceId,
    composante,
    valeur,
    saisiPar,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'notes';
  @override
  VerificationContext validateIntegrity(
    Insertable<Note> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('eleve_id')) {
      context.handle(
        _eleveIdMeta,
        eleveId.isAcceptableOrUnknown(data['eleve_id']!, _eleveIdMeta),
      );
    } else if (isInserting) {
      context.missing(_eleveIdMeta);
    }
    if (data.containsKey('classe_matiere_id')) {
      context.handle(
        _classeMatiereIdMeta,
        classeMatiereId.isAcceptableOrUnknown(
          data['classe_matiere_id']!,
          _classeMatiereIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_classeMatiereIdMeta);
    }
    if (data.containsKey('sequence_id')) {
      context.handle(
        _sequenceIdMeta,
        sequenceId.isAcceptableOrUnknown(data['sequence_id']!, _sequenceIdMeta),
      );
    }
    if (data.containsKey('composante')) {
      context.handle(
        _composanteMeta,
        composante.isAcceptableOrUnknown(data['composante']!, _composanteMeta),
      );
    }
    if (data.containsKey('valeur')) {
      context.handle(
        _valeurMeta,
        valeur.isAcceptableOrUnknown(data['valeur']!, _valeurMeta),
      );
    }
    if (data.containsKey('saisi_par')) {
      context.handle(
        _saisiParMeta,
        saisiPar.isAcceptableOrUnknown(data['saisi_par']!, _saisiParMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Note map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Note(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      eleveId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}eleve_id'],
      )!,
      classeMatiereId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_matiere_id'],
      )!,
      sequenceId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}sequence_id'],
      ),
      composante: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}composante'],
      ),
      valeur: attachedDatabase.typeMapping.read(
        DriftSqlType.double,
        data['${effectivePrefix}valeur'],
      ),
      saisiPar: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}saisi_par'],
      ),
    );
  }

  @override
  $NotesTable createAlias(String alias) {
    return $NotesTable(attachedDatabase, alias);
  }
}

class Note extends DataClass implements Insertable<Note> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int eleveId;
  final int classeMatiereId;
  final int? sequenceId;
  final String? composante;
  final double? valeur;
  final int? saisiPar;
  const Note({
    required this.id,
    required this.etatSync,
    required this.eleveId,
    required this.classeMatiereId,
    this.sequenceId,
    this.composante,
    this.valeur,
    this.saisiPar,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['eleve_id'] = Variable<int>(eleveId);
    map['classe_matiere_id'] = Variable<int>(classeMatiereId);
    if (!nullToAbsent || sequenceId != null) {
      map['sequence_id'] = Variable<int>(sequenceId);
    }
    if (!nullToAbsent || composante != null) {
      map['composante'] = Variable<String>(composante);
    }
    if (!nullToAbsent || valeur != null) {
      map['valeur'] = Variable<double>(valeur);
    }
    if (!nullToAbsent || saisiPar != null) {
      map['saisi_par'] = Variable<int>(saisiPar);
    }
    return map;
  }

  NotesCompanion toCompanion(bool nullToAbsent) {
    return NotesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      eleveId: Value(eleveId),
      classeMatiereId: Value(classeMatiereId),
      sequenceId: sequenceId == null && nullToAbsent
          ? const Value.absent()
          : Value(sequenceId),
      composante: composante == null && nullToAbsent
          ? const Value.absent()
          : Value(composante),
      valeur: valeur == null && nullToAbsent
          ? const Value.absent()
          : Value(valeur),
      saisiPar: saisiPar == null && nullToAbsent
          ? const Value.absent()
          : Value(saisiPar),
    );
  }

  factory Note.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Note(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      eleveId: serializer.fromJson<int>(json['eleveId']),
      classeMatiereId: serializer.fromJson<int>(json['classeMatiereId']),
      sequenceId: serializer.fromJson<int?>(json['sequenceId']),
      composante: serializer.fromJson<String?>(json['composante']),
      valeur: serializer.fromJson<double?>(json['valeur']),
      saisiPar: serializer.fromJson<int?>(json['saisiPar']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'eleveId': serializer.toJson<int>(eleveId),
      'classeMatiereId': serializer.toJson<int>(classeMatiereId),
      'sequenceId': serializer.toJson<int?>(sequenceId),
      'composante': serializer.toJson<String?>(composante),
      'valeur': serializer.toJson<double?>(valeur),
      'saisiPar': serializer.toJson<int?>(saisiPar),
    };
  }

  Note copyWith({
    int? id,
    String? etatSync,
    int? eleveId,
    int? classeMatiereId,
    Value<int?> sequenceId = const Value.absent(),
    Value<String?> composante = const Value.absent(),
    Value<double?> valeur = const Value.absent(),
    Value<int?> saisiPar = const Value.absent(),
  }) => Note(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    eleveId: eleveId ?? this.eleveId,
    classeMatiereId: classeMatiereId ?? this.classeMatiereId,
    sequenceId: sequenceId.present ? sequenceId.value : this.sequenceId,
    composante: composante.present ? composante.value : this.composante,
    valeur: valeur.present ? valeur.value : this.valeur,
    saisiPar: saisiPar.present ? saisiPar.value : this.saisiPar,
  );
  Note copyWithCompanion(NotesCompanion data) {
    return Note(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      eleveId: data.eleveId.present ? data.eleveId.value : this.eleveId,
      classeMatiereId: data.classeMatiereId.present
          ? data.classeMatiereId.value
          : this.classeMatiereId,
      sequenceId: data.sequenceId.present
          ? data.sequenceId.value
          : this.sequenceId,
      composante: data.composante.present
          ? data.composante.value
          : this.composante,
      valeur: data.valeur.present ? data.valeur.value : this.valeur,
      saisiPar: data.saisiPar.present ? data.saisiPar.value : this.saisiPar,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Note(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('eleveId: $eleveId, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('sequenceId: $sequenceId, ')
          ..write('composante: $composante, ')
          ..write('valeur: $valeur, ')
          ..write('saisiPar: $saisiPar')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    eleveId,
    classeMatiereId,
    sequenceId,
    composante,
    valeur,
    saisiPar,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Note &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.eleveId == this.eleveId &&
          other.classeMatiereId == this.classeMatiereId &&
          other.sequenceId == this.sequenceId &&
          other.composante == this.composante &&
          other.valeur == this.valeur &&
          other.saisiPar == this.saisiPar);
}

class NotesCompanion extends UpdateCompanion<Note> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> eleveId;
  final Value<int> classeMatiereId;
  final Value<int?> sequenceId;
  final Value<String?> composante;
  final Value<double?> valeur;
  final Value<int?> saisiPar;
  const NotesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.eleveId = const Value.absent(),
    this.classeMatiereId = const Value.absent(),
    this.sequenceId = const Value.absent(),
    this.composante = const Value.absent(),
    this.valeur = const Value.absent(),
    this.saisiPar = const Value.absent(),
  });
  NotesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int eleveId,
    required int classeMatiereId,
    this.sequenceId = const Value.absent(),
    this.composante = const Value.absent(),
    this.valeur = const Value.absent(),
    this.saisiPar = const Value.absent(),
  }) : eleveId = Value(eleveId),
       classeMatiereId = Value(classeMatiereId);
  static Insertable<Note> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? eleveId,
    Expression<int>? classeMatiereId,
    Expression<int>? sequenceId,
    Expression<String>? composante,
    Expression<double>? valeur,
    Expression<int>? saisiPar,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (eleveId != null) 'eleve_id': eleveId,
      if (classeMatiereId != null) 'classe_matiere_id': classeMatiereId,
      if (sequenceId != null) 'sequence_id': sequenceId,
      if (composante != null) 'composante': composante,
      if (valeur != null) 'valeur': valeur,
      if (saisiPar != null) 'saisi_par': saisiPar,
    });
  }

  NotesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? eleveId,
    Value<int>? classeMatiereId,
    Value<int?>? sequenceId,
    Value<String?>? composante,
    Value<double?>? valeur,
    Value<int?>? saisiPar,
  }) {
    return NotesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      eleveId: eleveId ?? this.eleveId,
      classeMatiereId: classeMatiereId ?? this.classeMatiereId,
      sequenceId: sequenceId ?? this.sequenceId,
      composante: composante ?? this.composante,
      valeur: valeur ?? this.valeur,
      saisiPar: saisiPar ?? this.saisiPar,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (eleveId.present) {
      map['eleve_id'] = Variable<int>(eleveId.value);
    }
    if (classeMatiereId.present) {
      map['classe_matiere_id'] = Variable<int>(classeMatiereId.value);
    }
    if (sequenceId.present) {
      map['sequence_id'] = Variable<int>(sequenceId.value);
    }
    if (composante.present) {
      map['composante'] = Variable<String>(composante.value);
    }
    if (valeur.present) {
      map['valeur'] = Variable<double>(valeur.value);
    }
    if (saisiPar.present) {
      map['saisi_par'] = Variable<int>(saisiPar.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('NotesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('eleveId: $eleveId, ')
          ..write('classeMatiereId: $classeMatiereId, ')
          ..write('sequenceId: $sequenceId, ')
          ..write('composante: $composante, ')
          ..write('valeur: $valeur, ')
          ..write('saisiPar: $saisiPar')
          ..write(')'))
        .toString();
  }
}

class $SanctionsTable extends Sanctions
    with TableInfo<$SanctionsTable, Sanction> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SanctionsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _eleveIdMeta = const VerificationMeta(
    'eleveId',
  );
  @override
  late final GeneratedColumn<int> eleveId = GeneratedColumn<int>(
    'eleve_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _classeIdMeta = const VerificationMeta(
    'classeId',
  );
  @override
  late final GeneratedColumn<int> classeId = GeneratedColumn<int>(
    'classe_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _trimestreIdMeta = const VerificationMeta(
    'trimestreId',
  );
  @override
  late final GeneratedColumn<int> trimestreId = GeneratedColumn<int>(
    'trimestre_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _typeMeta = const VerificationMeta('type');
  @override
  late final GeneratedColumn<String> type = GeneratedColumn<String>(
    'type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _dureeJoursMeta = const VerificationMeta(
    'dureeJours',
  );
  @override
  late final GeneratedColumn<int> dureeJours = GeneratedColumn<int>(
    'duree_jours',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dateDebutMeta = const VerificationMeta(
    'dateDebut',
  );
  @override
  late final GeneratedColumn<String> dateDebut = GeneratedColumn<String>(
    'date_debut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dateFinMeta = const VerificationMeta(
    'dateFin',
  );
  @override
  late final GeneratedColumn<String> dateFin = GeneratedColumn<String>(
    'date_fin',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _motifMeta = const VerificationMeta('motif');
  @override
  late final GeneratedColumn<String> motif = GeneratedColumn<String>(
    'motif',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _commentaireMeta = const VerificationMeta(
    'commentaire',
  );
  @override
  late final GeneratedColumn<String> commentaire = GeneratedColumn<String>(
    'commentaire',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _dateSanctionMeta = const VerificationMeta(
    'dateSanction',
  );
  @override
  late final GeneratedColumn<String> dateSanction = GeneratedColumn<String>(
    'date_sanction',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _statutMeta = const VerificationMeta('statut');
  @override
  late final GeneratedColumn<String> statut = GeneratedColumn<String>(
    'statut',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _impacteBulletinMeta = const VerificationMeta(
    'impacteBulletin',
  );
  @override
  late final GeneratedColumn<bool> impacteBulletin = GeneratedColumn<bool>(
    'impacte_bulletin',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("impacte_bulletin" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _enregistreParMeta = const VerificationMeta(
    'enregistrePar',
  );
  @override
  late final GeneratedColumn<int> enregistrePar = GeneratedColumn<int>(
    'enregistre_par',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    eleveId,
    classeId,
    trimestreId,
    type,
    dureeJours,
    dateDebut,
    dateFin,
    motif,
    commentaire,
    dateSanction,
    statut,
    impacteBulletin,
    enregistrePar,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'sanctions';
  @override
  VerificationContext validateIntegrity(
    Insertable<Sanction> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('eleve_id')) {
      context.handle(
        _eleveIdMeta,
        eleveId.isAcceptableOrUnknown(data['eleve_id']!, _eleveIdMeta),
      );
    } else if (isInserting) {
      context.missing(_eleveIdMeta);
    }
    if (data.containsKey('classe_id')) {
      context.handle(
        _classeIdMeta,
        classeId.isAcceptableOrUnknown(data['classe_id']!, _classeIdMeta),
      );
    }
    if (data.containsKey('trimestre_id')) {
      context.handle(
        _trimestreIdMeta,
        trimestreId.isAcceptableOrUnknown(
          data['trimestre_id']!,
          _trimestreIdMeta,
        ),
      );
    }
    if (data.containsKey('type')) {
      context.handle(
        _typeMeta,
        type.isAcceptableOrUnknown(data['type']!, _typeMeta),
      );
    } else if (isInserting) {
      context.missing(_typeMeta);
    }
    if (data.containsKey('duree_jours')) {
      context.handle(
        _dureeJoursMeta,
        dureeJours.isAcceptableOrUnknown(data['duree_jours']!, _dureeJoursMeta),
      );
    }
    if (data.containsKey('date_debut')) {
      context.handle(
        _dateDebutMeta,
        dateDebut.isAcceptableOrUnknown(data['date_debut']!, _dateDebutMeta),
      );
    }
    if (data.containsKey('date_fin')) {
      context.handle(
        _dateFinMeta,
        dateFin.isAcceptableOrUnknown(data['date_fin']!, _dateFinMeta),
      );
    }
    if (data.containsKey('motif')) {
      context.handle(
        _motifMeta,
        motif.isAcceptableOrUnknown(data['motif']!, _motifMeta),
      );
    }
    if (data.containsKey('commentaire')) {
      context.handle(
        _commentaireMeta,
        commentaire.isAcceptableOrUnknown(
          data['commentaire']!,
          _commentaireMeta,
        ),
      );
    }
    if (data.containsKey('date_sanction')) {
      context.handle(
        _dateSanctionMeta,
        dateSanction.isAcceptableOrUnknown(
          data['date_sanction']!,
          _dateSanctionMeta,
        ),
      );
    }
    if (data.containsKey('statut')) {
      context.handle(
        _statutMeta,
        statut.isAcceptableOrUnknown(data['statut']!, _statutMeta),
      );
    }
    if (data.containsKey('impacte_bulletin')) {
      context.handle(
        _impacteBulletinMeta,
        impacteBulletin.isAcceptableOrUnknown(
          data['impacte_bulletin']!,
          _impacteBulletinMeta,
        ),
      );
    }
    if (data.containsKey('enregistre_par')) {
      context.handle(
        _enregistreParMeta,
        enregistrePar.isAcceptableOrUnknown(
          data['enregistre_par']!,
          _enregistreParMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Sanction map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Sanction(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      eleveId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}eleve_id'],
      )!,
      classeId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}classe_id'],
      ),
      trimestreId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}trimestre_id'],
      ),
      type: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}type'],
      )!,
      dureeJours: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}duree_jours'],
      ),
      dateDebut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_debut'],
      ),
      dateFin: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_fin'],
      ),
      motif: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}motif'],
      ),
      commentaire: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}commentaire'],
      ),
      dateSanction: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}date_sanction'],
      ),
      statut: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}statut'],
      ),
      impacteBulletin: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}impacte_bulletin'],
      )!,
      enregistrePar: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}enregistre_par'],
      ),
    );
  }

  @override
  $SanctionsTable createAlias(String alias) {
    return $SanctionsTable(attachedDatabase, alias);
  }
}

class Sanction extends DataClass implements Insertable<Sanction> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int eleveId;
  final int? classeId;
  final int? trimestreId;
  final String type;
  final int? dureeJours;
  final String? dateDebut;
  final String? dateFin;
  final String? motif;
  final String? commentaire;
  final String? dateSanction;
  final String? statut;
  final bool impacteBulletin;
  final int? enregistrePar;
  const Sanction({
    required this.id,
    required this.etatSync,
    required this.eleveId,
    this.classeId,
    this.trimestreId,
    required this.type,
    this.dureeJours,
    this.dateDebut,
    this.dateFin,
    this.motif,
    this.commentaire,
    this.dateSanction,
    this.statut,
    required this.impacteBulletin,
    this.enregistrePar,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['eleve_id'] = Variable<int>(eleveId);
    if (!nullToAbsent || classeId != null) {
      map['classe_id'] = Variable<int>(classeId);
    }
    if (!nullToAbsent || trimestreId != null) {
      map['trimestre_id'] = Variable<int>(trimestreId);
    }
    map['type'] = Variable<String>(type);
    if (!nullToAbsent || dureeJours != null) {
      map['duree_jours'] = Variable<int>(dureeJours);
    }
    if (!nullToAbsent || dateDebut != null) {
      map['date_debut'] = Variable<String>(dateDebut);
    }
    if (!nullToAbsent || dateFin != null) {
      map['date_fin'] = Variable<String>(dateFin);
    }
    if (!nullToAbsent || motif != null) {
      map['motif'] = Variable<String>(motif);
    }
    if (!nullToAbsent || commentaire != null) {
      map['commentaire'] = Variable<String>(commentaire);
    }
    if (!nullToAbsent || dateSanction != null) {
      map['date_sanction'] = Variable<String>(dateSanction);
    }
    if (!nullToAbsent || statut != null) {
      map['statut'] = Variable<String>(statut);
    }
    map['impacte_bulletin'] = Variable<bool>(impacteBulletin);
    if (!nullToAbsent || enregistrePar != null) {
      map['enregistre_par'] = Variable<int>(enregistrePar);
    }
    return map;
  }

  SanctionsCompanion toCompanion(bool nullToAbsent) {
    return SanctionsCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      eleveId: Value(eleveId),
      classeId: classeId == null && nullToAbsent
          ? const Value.absent()
          : Value(classeId),
      trimestreId: trimestreId == null && nullToAbsent
          ? const Value.absent()
          : Value(trimestreId),
      type: Value(type),
      dureeJours: dureeJours == null && nullToAbsent
          ? const Value.absent()
          : Value(dureeJours),
      dateDebut: dateDebut == null && nullToAbsent
          ? const Value.absent()
          : Value(dateDebut),
      dateFin: dateFin == null && nullToAbsent
          ? const Value.absent()
          : Value(dateFin),
      motif: motif == null && nullToAbsent
          ? const Value.absent()
          : Value(motif),
      commentaire: commentaire == null && nullToAbsent
          ? const Value.absent()
          : Value(commentaire),
      dateSanction: dateSanction == null && nullToAbsent
          ? const Value.absent()
          : Value(dateSanction),
      statut: statut == null && nullToAbsent
          ? const Value.absent()
          : Value(statut),
      impacteBulletin: Value(impacteBulletin),
      enregistrePar: enregistrePar == null && nullToAbsent
          ? const Value.absent()
          : Value(enregistrePar),
    );
  }

  factory Sanction.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Sanction(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      eleveId: serializer.fromJson<int>(json['eleveId']),
      classeId: serializer.fromJson<int?>(json['classeId']),
      trimestreId: serializer.fromJson<int?>(json['trimestreId']),
      type: serializer.fromJson<String>(json['type']),
      dureeJours: serializer.fromJson<int?>(json['dureeJours']),
      dateDebut: serializer.fromJson<String?>(json['dateDebut']),
      dateFin: serializer.fromJson<String?>(json['dateFin']),
      motif: serializer.fromJson<String?>(json['motif']),
      commentaire: serializer.fromJson<String?>(json['commentaire']),
      dateSanction: serializer.fromJson<String?>(json['dateSanction']),
      statut: serializer.fromJson<String?>(json['statut']),
      impacteBulletin: serializer.fromJson<bool>(json['impacteBulletin']),
      enregistrePar: serializer.fromJson<int?>(json['enregistrePar']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'eleveId': serializer.toJson<int>(eleveId),
      'classeId': serializer.toJson<int?>(classeId),
      'trimestreId': serializer.toJson<int?>(trimestreId),
      'type': serializer.toJson<String>(type),
      'dureeJours': serializer.toJson<int?>(dureeJours),
      'dateDebut': serializer.toJson<String?>(dateDebut),
      'dateFin': serializer.toJson<String?>(dateFin),
      'motif': serializer.toJson<String?>(motif),
      'commentaire': serializer.toJson<String?>(commentaire),
      'dateSanction': serializer.toJson<String?>(dateSanction),
      'statut': serializer.toJson<String?>(statut),
      'impacteBulletin': serializer.toJson<bool>(impacteBulletin),
      'enregistrePar': serializer.toJson<int?>(enregistrePar),
    };
  }

  Sanction copyWith({
    int? id,
    String? etatSync,
    int? eleveId,
    Value<int?> classeId = const Value.absent(),
    Value<int?> trimestreId = const Value.absent(),
    String? type,
    Value<int?> dureeJours = const Value.absent(),
    Value<String?> dateDebut = const Value.absent(),
    Value<String?> dateFin = const Value.absent(),
    Value<String?> motif = const Value.absent(),
    Value<String?> commentaire = const Value.absent(),
    Value<String?> dateSanction = const Value.absent(),
    Value<String?> statut = const Value.absent(),
    bool? impacteBulletin,
    Value<int?> enregistrePar = const Value.absent(),
  }) => Sanction(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    eleveId: eleveId ?? this.eleveId,
    classeId: classeId.present ? classeId.value : this.classeId,
    trimestreId: trimestreId.present ? trimestreId.value : this.trimestreId,
    type: type ?? this.type,
    dureeJours: dureeJours.present ? dureeJours.value : this.dureeJours,
    dateDebut: dateDebut.present ? dateDebut.value : this.dateDebut,
    dateFin: dateFin.present ? dateFin.value : this.dateFin,
    motif: motif.present ? motif.value : this.motif,
    commentaire: commentaire.present ? commentaire.value : this.commentaire,
    dateSanction: dateSanction.present ? dateSanction.value : this.dateSanction,
    statut: statut.present ? statut.value : this.statut,
    impacteBulletin: impacteBulletin ?? this.impacteBulletin,
    enregistrePar: enregistrePar.present
        ? enregistrePar.value
        : this.enregistrePar,
  );
  Sanction copyWithCompanion(SanctionsCompanion data) {
    return Sanction(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      eleveId: data.eleveId.present ? data.eleveId.value : this.eleveId,
      classeId: data.classeId.present ? data.classeId.value : this.classeId,
      trimestreId: data.trimestreId.present
          ? data.trimestreId.value
          : this.trimestreId,
      type: data.type.present ? data.type.value : this.type,
      dureeJours: data.dureeJours.present
          ? data.dureeJours.value
          : this.dureeJours,
      dateDebut: data.dateDebut.present ? data.dateDebut.value : this.dateDebut,
      dateFin: data.dateFin.present ? data.dateFin.value : this.dateFin,
      motif: data.motif.present ? data.motif.value : this.motif,
      commentaire: data.commentaire.present
          ? data.commentaire.value
          : this.commentaire,
      dateSanction: data.dateSanction.present
          ? data.dateSanction.value
          : this.dateSanction,
      statut: data.statut.present ? data.statut.value : this.statut,
      impacteBulletin: data.impacteBulletin.present
          ? data.impacteBulletin.value
          : this.impacteBulletin,
      enregistrePar: data.enregistrePar.present
          ? data.enregistrePar.value
          : this.enregistrePar,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Sanction(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('eleveId: $eleveId, ')
          ..write('classeId: $classeId, ')
          ..write('trimestreId: $trimestreId, ')
          ..write('type: $type, ')
          ..write('dureeJours: $dureeJours, ')
          ..write('dateDebut: $dateDebut, ')
          ..write('dateFin: $dateFin, ')
          ..write('motif: $motif, ')
          ..write('commentaire: $commentaire, ')
          ..write('dateSanction: $dateSanction, ')
          ..write('statut: $statut, ')
          ..write('impacteBulletin: $impacteBulletin, ')
          ..write('enregistrePar: $enregistrePar')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    eleveId,
    classeId,
    trimestreId,
    type,
    dureeJours,
    dateDebut,
    dateFin,
    motif,
    commentaire,
    dateSanction,
    statut,
    impacteBulletin,
    enregistrePar,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Sanction &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.eleveId == this.eleveId &&
          other.classeId == this.classeId &&
          other.trimestreId == this.trimestreId &&
          other.type == this.type &&
          other.dureeJours == this.dureeJours &&
          other.dateDebut == this.dateDebut &&
          other.dateFin == this.dateFin &&
          other.motif == this.motif &&
          other.commentaire == this.commentaire &&
          other.dateSanction == this.dateSanction &&
          other.statut == this.statut &&
          other.impacteBulletin == this.impacteBulletin &&
          other.enregistrePar == this.enregistrePar);
}

class SanctionsCompanion extends UpdateCompanion<Sanction> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> eleveId;
  final Value<int?> classeId;
  final Value<int?> trimestreId;
  final Value<String> type;
  final Value<int?> dureeJours;
  final Value<String?> dateDebut;
  final Value<String?> dateFin;
  final Value<String?> motif;
  final Value<String?> commentaire;
  final Value<String?> dateSanction;
  final Value<String?> statut;
  final Value<bool> impacteBulletin;
  final Value<int?> enregistrePar;
  const SanctionsCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.eleveId = const Value.absent(),
    this.classeId = const Value.absent(),
    this.trimestreId = const Value.absent(),
    this.type = const Value.absent(),
    this.dureeJours = const Value.absent(),
    this.dateDebut = const Value.absent(),
    this.dateFin = const Value.absent(),
    this.motif = const Value.absent(),
    this.commentaire = const Value.absent(),
    this.dateSanction = const Value.absent(),
    this.statut = const Value.absent(),
    this.impacteBulletin = const Value.absent(),
    this.enregistrePar = const Value.absent(),
  });
  SanctionsCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int eleveId,
    this.classeId = const Value.absent(),
    this.trimestreId = const Value.absent(),
    required String type,
    this.dureeJours = const Value.absent(),
    this.dateDebut = const Value.absent(),
    this.dateFin = const Value.absent(),
    this.motif = const Value.absent(),
    this.commentaire = const Value.absent(),
    this.dateSanction = const Value.absent(),
    this.statut = const Value.absent(),
    this.impacteBulletin = const Value.absent(),
    this.enregistrePar = const Value.absent(),
  }) : eleveId = Value(eleveId),
       type = Value(type);
  static Insertable<Sanction> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? eleveId,
    Expression<int>? classeId,
    Expression<int>? trimestreId,
    Expression<String>? type,
    Expression<int>? dureeJours,
    Expression<String>? dateDebut,
    Expression<String>? dateFin,
    Expression<String>? motif,
    Expression<String>? commentaire,
    Expression<String>? dateSanction,
    Expression<String>? statut,
    Expression<bool>? impacteBulletin,
    Expression<int>? enregistrePar,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (eleveId != null) 'eleve_id': eleveId,
      if (classeId != null) 'classe_id': classeId,
      if (trimestreId != null) 'trimestre_id': trimestreId,
      if (type != null) 'type': type,
      if (dureeJours != null) 'duree_jours': dureeJours,
      if (dateDebut != null) 'date_debut': dateDebut,
      if (dateFin != null) 'date_fin': dateFin,
      if (motif != null) 'motif': motif,
      if (commentaire != null) 'commentaire': commentaire,
      if (dateSanction != null) 'date_sanction': dateSanction,
      if (statut != null) 'statut': statut,
      if (impacteBulletin != null) 'impacte_bulletin': impacteBulletin,
      if (enregistrePar != null) 'enregistre_par': enregistrePar,
    });
  }

  SanctionsCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? eleveId,
    Value<int?>? classeId,
    Value<int?>? trimestreId,
    Value<String>? type,
    Value<int?>? dureeJours,
    Value<String?>? dateDebut,
    Value<String?>? dateFin,
    Value<String?>? motif,
    Value<String?>? commentaire,
    Value<String?>? dateSanction,
    Value<String?>? statut,
    Value<bool>? impacteBulletin,
    Value<int?>? enregistrePar,
  }) {
    return SanctionsCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      eleveId: eleveId ?? this.eleveId,
      classeId: classeId ?? this.classeId,
      trimestreId: trimestreId ?? this.trimestreId,
      type: type ?? this.type,
      dureeJours: dureeJours ?? this.dureeJours,
      dateDebut: dateDebut ?? this.dateDebut,
      dateFin: dateFin ?? this.dateFin,
      motif: motif ?? this.motif,
      commentaire: commentaire ?? this.commentaire,
      dateSanction: dateSanction ?? this.dateSanction,
      statut: statut ?? this.statut,
      impacteBulletin: impacteBulletin ?? this.impacteBulletin,
      enregistrePar: enregistrePar ?? this.enregistrePar,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (eleveId.present) {
      map['eleve_id'] = Variable<int>(eleveId.value);
    }
    if (classeId.present) {
      map['classe_id'] = Variable<int>(classeId.value);
    }
    if (trimestreId.present) {
      map['trimestre_id'] = Variable<int>(trimestreId.value);
    }
    if (type.present) {
      map['type'] = Variable<String>(type.value);
    }
    if (dureeJours.present) {
      map['duree_jours'] = Variable<int>(dureeJours.value);
    }
    if (dateDebut.present) {
      map['date_debut'] = Variable<String>(dateDebut.value);
    }
    if (dateFin.present) {
      map['date_fin'] = Variable<String>(dateFin.value);
    }
    if (motif.present) {
      map['motif'] = Variable<String>(motif.value);
    }
    if (commentaire.present) {
      map['commentaire'] = Variable<String>(commentaire.value);
    }
    if (dateSanction.present) {
      map['date_sanction'] = Variable<String>(dateSanction.value);
    }
    if (statut.present) {
      map['statut'] = Variable<String>(statut.value);
    }
    if (impacteBulletin.present) {
      map['impacte_bulletin'] = Variable<bool>(impacteBulletin.value);
    }
    if (enregistrePar.present) {
      map['enregistre_par'] = Variable<int>(enregistrePar.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SanctionsCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('eleveId: $eleveId, ')
          ..write('classeId: $classeId, ')
          ..write('trimestreId: $trimestreId, ')
          ..write('type: $type, ')
          ..write('dureeJours: $dureeJours, ')
          ..write('dateDebut: $dateDebut, ')
          ..write('dateFin: $dateFin, ')
          ..write('motif: $motif, ')
          ..write('commentaire: $commentaire, ')
          ..write('dateSanction: $dateSanction, ')
          ..write('statut: $statut, ')
          ..write('impacteBulletin: $impacteBulletin, ')
          ..write('enregistrePar: $enregistrePar')
          ..write(')'))
        .toString();
  }
}

class $AnnoncesTable extends Annonces with TableInfo<$AnnoncesTable, Annonce> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $AnnoncesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _titreMeta = const VerificationMeta('titre');
  @override
  late final GeneratedColumn<String> titre = GeneratedColumn<String>(
    'titre',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _contenuMeta = const VerificationMeta(
    'contenu',
  );
  @override
  late final GeneratedColumn<String> contenu = GeneratedColumn<String>(
    'contenu',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _publieParMeta = const VerificationMeta(
    'publiePar',
  );
  @override
  late final GeneratedColumn<int> publiePar = GeneratedColumn<int>(
    'publie_par',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _publieeLeMeta = const VerificationMeta(
    'publieeLe',
  );
  @override
  late final GeneratedColumn<String> publieeLe = GeneratedColumn<String>(
    'publiee_le',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    titre,
    contenu,
    publiePar,
    publieeLe,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'annonces';
  @override
  VerificationContext validateIntegrity(
    Insertable<Annonce> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('titre')) {
      context.handle(
        _titreMeta,
        titre.isAcceptableOrUnknown(data['titre']!, _titreMeta),
      );
    } else if (isInserting) {
      context.missing(_titreMeta);
    }
    if (data.containsKey('contenu')) {
      context.handle(
        _contenuMeta,
        contenu.isAcceptableOrUnknown(data['contenu']!, _contenuMeta),
      );
    }
    if (data.containsKey('publie_par')) {
      context.handle(
        _publieParMeta,
        publiePar.isAcceptableOrUnknown(data['publie_par']!, _publieParMeta),
      );
    }
    if (data.containsKey('publiee_le')) {
      context.handle(
        _publieeLeMeta,
        publieeLe.isAcceptableOrUnknown(data['publiee_le']!, _publieeLeMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Annonce map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Annonce(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      titre: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}titre'],
      )!,
      contenu: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}contenu'],
      ),
      publiePar: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}publie_par'],
      ),
      publieeLe: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}publiee_le'],
      ),
    );
  }

  @override
  $AnnoncesTable createAlias(String alias) {
    return $AnnoncesTable(attachedDatabase, alias);
  }
}

class Annonce extends DataClass implements Insertable<Annonce> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final String titre;
  final String? contenu;
  final int? publiePar;
  final String? publieeLe;
  const Annonce({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    required this.titre,
    this.contenu,
    this.publiePar,
    this.publieeLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    map['titre'] = Variable<String>(titre);
    if (!nullToAbsent || contenu != null) {
      map['contenu'] = Variable<String>(contenu);
    }
    if (!nullToAbsent || publiePar != null) {
      map['publie_par'] = Variable<int>(publiePar);
    }
    if (!nullToAbsent || publieeLe != null) {
      map['publiee_le'] = Variable<String>(publieeLe);
    }
    return map;
  }

  AnnoncesCompanion toCompanion(bool nullToAbsent) {
    return AnnoncesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      titre: Value(titre),
      contenu: contenu == null && nullToAbsent
          ? const Value.absent()
          : Value(contenu),
      publiePar: publiePar == null && nullToAbsent
          ? const Value.absent()
          : Value(publiePar),
      publieeLe: publieeLe == null && nullToAbsent
          ? const Value.absent()
          : Value(publieeLe),
    );
  }

  factory Annonce.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Annonce(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      titre: serializer.fromJson<String>(json['titre']),
      contenu: serializer.fromJson<String?>(json['contenu']),
      publiePar: serializer.fromJson<int?>(json['publiePar']),
      publieeLe: serializer.fromJson<String?>(json['publieeLe']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'titre': serializer.toJson<String>(titre),
      'contenu': serializer.toJson<String?>(contenu),
      'publiePar': serializer.toJson<int?>(publiePar),
      'publieeLe': serializer.toJson<String?>(publieeLe),
    };
  }

  Annonce copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    String? titre,
    Value<String?> contenu = const Value.absent(),
    Value<int?> publiePar = const Value.absent(),
    Value<String?> publieeLe = const Value.absent(),
  }) => Annonce(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    titre: titre ?? this.titre,
    contenu: contenu.present ? contenu.value : this.contenu,
    publiePar: publiePar.present ? publiePar.value : this.publiePar,
    publieeLe: publieeLe.present ? publieeLe.value : this.publieeLe,
  );
  Annonce copyWithCompanion(AnnoncesCompanion data) {
    return Annonce(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      titre: data.titre.present ? data.titre.value : this.titre,
      contenu: data.contenu.present ? data.contenu.value : this.contenu,
      publiePar: data.publiePar.present ? data.publiePar.value : this.publiePar,
      publieeLe: data.publieeLe.present ? data.publieeLe.value : this.publieeLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Annonce(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('titre: $titre, ')
          ..write('contenu: $contenu, ')
          ..write('publiePar: $publiePar, ')
          ..write('publieeLe: $publieeLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(id, etatSync, schoolId, titre, contenu, publiePar, publieeLe);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Annonce &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.titre == this.titre &&
          other.contenu == this.contenu &&
          other.publiePar == this.publiePar &&
          other.publieeLe == this.publieeLe);
}

class AnnoncesCompanion extends UpdateCompanion<Annonce> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<String> titre;
  final Value<String?> contenu;
  final Value<int?> publiePar;
  final Value<String?> publieeLe;
  const AnnoncesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.titre = const Value.absent(),
    this.contenu = const Value.absent(),
    this.publiePar = const Value.absent(),
    this.publieeLe = const Value.absent(),
  });
  AnnoncesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    required String titre,
    this.contenu = const Value.absent(),
    this.publiePar = const Value.absent(),
    this.publieeLe = const Value.absent(),
  }) : schoolId = Value(schoolId),
       titre = Value(titre);
  static Insertable<Annonce> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<String>? titre,
    Expression<String>? contenu,
    Expression<int>? publiePar,
    Expression<String>? publieeLe,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (titre != null) 'titre': titre,
      if (contenu != null) 'contenu': contenu,
      if (publiePar != null) 'publie_par': publiePar,
      if (publieeLe != null) 'publiee_le': publieeLe,
    });
  }

  AnnoncesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<String>? titre,
    Value<String?>? contenu,
    Value<int?>? publiePar,
    Value<String?>? publieeLe,
  }) {
    return AnnoncesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      titre: titre ?? this.titre,
      contenu: contenu ?? this.contenu,
      publiePar: publiePar ?? this.publiePar,
      publieeLe: publieeLe ?? this.publieeLe,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (titre.present) {
      map['titre'] = Variable<String>(titre.value);
    }
    if (contenu.present) {
      map['contenu'] = Variable<String>(contenu.value);
    }
    if (publiePar.present) {
      map['publie_par'] = Variable<int>(publiePar.value);
    }
    if (publieeLe.present) {
      map['publiee_le'] = Variable<String>(publieeLe.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('AnnoncesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('titre: $titre, ')
          ..write('contenu: $contenu, ')
          ..write('publiePar: $publiePar, ')
          ..write('publieeLe: $publieeLe')
          ..write(')'))
        .toString();
  }
}

class $NotificationsInternesTable extends NotificationsInternes
    with TableInfo<$NotificationsInternesTable, NotificationsInterne> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $NotificationsInternesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _etatSyncMeta = const VerificationMeta(
    'etatSync',
  );
  @override
  late final GeneratedColumn<String> etatSync = GeneratedColumn<String>(
    'etat_sync',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synchro'),
  );
  static const VerificationMeta _schoolIdMeta = const VerificationMeta(
    'schoolId',
  );
  @override
  late final GeneratedColumn<int> schoolId = GeneratedColumn<int>(
    'school_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<int> userId = GeneratedColumn<int>(
    'user_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _typeMeta = const VerificationMeta('type');
  @override
  late final GeneratedColumn<String> type = GeneratedColumn<String>(
    'type',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _titreMeta = const VerificationMeta('titre');
  @override
  late final GeneratedColumn<String> titre = GeneratedColumn<String>(
    'titre',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _messageMeta = const VerificationMeta(
    'message',
  );
  @override
  late final GeneratedColumn<String> message = GeneratedColumn<String>(
    'message',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _lienMeta = const VerificationMeta('lien');
  @override
  late final GeneratedColumn<String> lien = GeneratedColumn<String>(
    'lien',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _luMeta = const VerificationMeta('lu');
  @override
  late final GeneratedColumn<bool> lu = GeneratedColumn<bool>(
    'lu',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("lu" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _luLeMeta = const VerificationMeta('luLe');
  @override
  late final GeneratedColumn<String> luLe = GeneratedColumn<String>(
    'lu_le',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    etatSync,
    schoolId,
    userId,
    type,
    titre,
    message,
    lien,
    lu,
    luLe,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'notifications_internes';
  @override
  VerificationContext validateIntegrity(
    Insertable<NotificationsInterne> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('etat_sync')) {
      context.handle(
        _etatSyncMeta,
        etatSync.isAcceptableOrUnknown(data['etat_sync']!, _etatSyncMeta),
      );
    }
    if (data.containsKey('school_id')) {
      context.handle(
        _schoolIdMeta,
        schoolId.isAcceptableOrUnknown(data['school_id']!, _schoolIdMeta),
      );
    } else if (isInserting) {
      context.missing(_schoolIdMeta);
    }
    if (data.containsKey('user_id')) {
      context.handle(
        _userIdMeta,
        userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta),
      );
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('type')) {
      context.handle(
        _typeMeta,
        type.isAcceptableOrUnknown(data['type']!, _typeMeta),
      );
    }
    if (data.containsKey('titre')) {
      context.handle(
        _titreMeta,
        titre.isAcceptableOrUnknown(data['titre']!, _titreMeta),
      );
    } else if (isInserting) {
      context.missing(_titreMeta);
    }
    if (data.containsKey('message')) {
      context.handle(
        _messageMeta,
        message.isAcceptableOrUnknown(data['message']!, _messageMeta),
      );
    }
    if (data.containsKey('lien')) {
      context.handle(
        _lienMeta,
        lien.isAcceptableOrUnknown(data['lien']!, _lienMeta),
      );
    }
    if (data.containsKey('lu')) {
      context.handle(_luMeta, lu.isAcceptableOrUnknown(data['lu']!, _luMeta));
    }
    if (data.containsKey('lu_le')) {
      context.handle(
        _luLeMeta,
        luLe.isAcceptableOrUnknown(data['lu_le']!, _luLeMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  NotificationsInterne map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return NotificationsInterne(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      etatSync: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}etat_sync'],
      )!,
      schoolId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}school_id'],
      )!,
      userId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}user_id'],
      )!,
      type: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}type'],
      ),
      titre: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}titre'],
      )!,
      message: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}message'],
      ),
      lien: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}lien'],
      ),
      lu: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}lu'],
      )!,
      luLe: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}lu_le'],
      ),
    );
  }

  @override
  $NotificationsInternesTable createAlias(String alias) {
    return $NotificationsInternesTable(attachedDatabase, alias);
  }
}

class NotificationsInterne extends DataClass
    implements Insertable<NotificationsInterne> {
  final int id;

  /// `synchro` | `enAttente` | `echoue`
  final String etatSync;
  final int schoolId;
  final int userId;
  final String? type;
  final String titre;
  final String? message;
  final String? lien;
  final bool lu;
  final String? luLe;
  const NotificationsInterne({
    required this.id,
    required this.etatSync,
    required this.schoolId,
    required this.userId,
    this.type,
    required this.titre,
    this.message,
    this.lien,
    required this.lu,
    this.luLe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['etat_sync'] = Variable<String>(etatSync);
    map['school_id'] = Variable<int>(schoolId);
    map['user_id'] = Variable<int>(userId);
    if (!nullToAbsent || type != null) {
      map['type'] = Variable<String>(type);
    }
    map['titre'] = Variable<String>(titre);
    if (!nullToAbsent || message != null) {
      map['message'] = Variable<String>(message);
    }
    if (!nullToAbsent || lien != null) {
      map['lien'] = Variable<String>(lien);
    }
    map['lu'] = Variable<bool>(lu);
    if (!nullToAbsent || luLe != null) {
      map['lu_le'] = Variable<String>(luLe);
    }
    return map;
  }

  NotificationsInternesCompanion toCompanion(bool nullToAbsent) {
    return NotificationsInternesCompanion(
      id: Value(id),
      etatSync: Value(etatSync),
      schoolId: Value(schoolId),
      userId: Value(userId),
      type: type == null && nullToAbsent ? const Value.absent() : Value(type),
      titre: Value(titre),
      message: message == null && nullToAbsent
          ? const Value.absent()
          : Value(message),
      lien: lien == null && nullToAbsent ? const Value.absent() : Value(lien),
      lu: Value(lu),
      luLe: luLe == null && nullToAbsent ? const Value.absent() : Value(luLe),
    );
  }

  factory NotificationsInterne.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return NotificationsInterne(
      id: serializer.fromJson<int>(json['id']),
      etatSync: serializer.fromJson<String>(json['etatSync']),
      schoolId: serializer.fromJson<int>(json['schoolId']),
      userId: serializer.fromJson<int>(json['userId']),
      type: serializer.fromJson<String?>(json['type']),
      titre: serializer.fromJson<String>(json['titre']),
      message: serializer.fromJson<String?>(json['message']),
      lien: serializer.fromJson<String?>(json['lien']),
      lu: serializer.fromJson<bool>(json['lu']),
      luLe: serializer.fromJson<String?>(json['luLe']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'etatSync': serializer.toJson<String>(etatSync),
      'schoolId': serializer.toJson<int>(schoolId),
      'userId': serializer.toJson<int>(userId),
      'type': serializer.toJson<String?>(type),
      'titre': serializer.toJson<String>(titre),
      'message': serializer.toJson<String?>(message),
      'lien': serializer.toJson<String?>(lien),
      'lu': serializer.toJson<bool>(lu),
      'luLe': serializer.toJson<String?>(luLe),
    };
  }

  NotificationsInterne copyWith({
    int? id,
    String? etatSync,
    int? schoolId,
    int? userId,
    Value<String?> type = const Value.absent(),
    String? titre,
    Value<String?> message = const Value.absent(),
    Value<String?> lien = const Value.absent(),
    bool? lu,
    Value<String?> luLe = const Value.absent(),
  }) => NotificationsInterne(
    id: id ?? this.id,
    etatSync: etatSync ?? this.etatSync,
    schoolId: schoolId ?? this.schoolId,
    userId: userId ?? this.userId,
    type: type.present ? type.value : this.type,
    titre: titre ?? this.titre,
    message: message.present ? message.value : this.message,
    lien: lien.present ? lien.value : this.lien,
    lu: lu ?? this.lu,
    luLe: luLe.present ? luLe.value : this.luLe,
  );
  NotificationsInterne copyWithCompanion(NotificationsInternesCompanion data) {
    return NotificationsInterne(
      id: data.id.present ? data.id.value : this.id,
      etatSync: data.etatSync.present ? data.etatSync.value : this.etatSync,
      schoolId: data.schoolId.present ? data.schoolId.value : this.schoolId,
      userId: data.userId.present ? data.userId.value : this.userId,
      type: data.type.present ? data.type.value : this.type,
      titre: data.titre.present ? data.titre.value : this.titre,
      message: data.message.present ? data.message.value : this.message,
      lien: data.lien.present ? data.lien.value : this.lien,
      lu: data.lu.present ? data.lu.value : this.lu,
      luLe: data.luLe.present ? data.luLe.value : this.luLe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('NotificationsInterne(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('userId: $userId, ')
          ..write('type: $type, ')
          ..write('titre: $titre, ')
          ..write('message: $message, ')
          ..write('lien: $lien, ')
          ..write('lu: $lu, ')
          ..write('luLe: $luLe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    etatSync,
    schoolId,
    userId,
    type,
    titre,
    message,
    lien,
    lu,
    luLe,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is NotificationsInterne &&
          other.id == this.id &&
          other.etatSync == this.etatSync &&
          other.schoolId == this.schoolId &&
          other.userId == this.userId &&
          other.type == this.type &&
          other.titre == this.titre &&
          other.message == this.message &&
          other.lien == this.lien &&
          other.lu == this.lu &&
          other.luLe == this.luLe);
}

class NotificationsInternesCompanion
    extends UpdateCompanion<NotificationsInterne> {
  final Value<int> id;
  final Value<String> etatSync;
  final Value<int> schoolId;
  final Value<int> userId;
  final Value<String?> type;
  final Value<String> titre;
  final Value<String?> message;
  final Value<String?> lien;
  final Value<bool> lu;
  final Value<String?> luLe;
  const NotificationsInternesCompanion({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    this.schoolId = const Value.absent(),
    this.userId = const Value.absent(),
    this.type = const Value.absent(),
    this.titre = const Value.absent(),
    this.message = const Value.absent(),
    this.lien = const Value.absent(),
    this.lu = const Value.absent(),
    this.luLe = const Value.absent(),
  });
  NotificationsInternesCompanion.insert({
    this.id = const Value.absent(),
    this.etatSync = const Value.absent(),
    required int schoolId,
    required int userId,
    this.type = const Value.absent(),
    required String titre,
    this.message = const Value.absent(),
    this.lien = const Value.absent(),
    this.lu = const Value.absent(),
    this.luLe = const Value.absent(),
  }) : schoolId = Value(schoolId),
       userId = Value(userId),
       titre = Value(titre);
  static Insertable<NotificationsInterne> custom({
    Expression<int>? id,
    Expression<String>? etatSync,
    Expression<int>? schoolId,
    Expression<int>? userId,
    Expression<String>? type,
    Expression<String>? titre,
    Expression<String>? message,
    Expression<String>? lien,
    Expression<bool>? lu,
    Expression<String>? luLe,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (etatSync != null) 'etat_sync': etatSync,
      if (schoolId != null) 'school_id': schoolId,
      if (userId != null) 'user_id': userId,
      if (type != null) 'type': type,
      if (titre != null) 'titre': titre,
      if (message != null) 'message': message,
      if (lien != null) 'lien': lien,
      if (lu != null) 'lu': lu,
      if (luLe != null) 'lu_le': luLe,
    });
  }

  NotificationsInternesCompanion copyWith({
    Value<int>? id,
    Value<String>? etatSync,
    Value<int>? schoolId,
    Value<int>? userId,
    Value<String?>? type,
    Value<String>? titre,
    Value<String?>? message,
    Value<String?>? lien,
    Value<bool>? lu,
    Value<String?>? luLe,
  }) {
    return NotificationsInternesCompanion(
      id: id ?? this.id,
      etatSync: etatSync ?? this.etatSync,
      schoolId: schoolId ?? this.schoolId,
      userId: userId ?? this.userId,
      type: type ?? this.type,
      titre: titre ?? this.titre,
      message: message ?? this.message,
      lien: lien ?? this.lien,
      lu: lu ?? this.lu,
      luLe: luLe ?? this.luLe,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (etatSync.present) {
      map['etat_sync'] = Variable<String>(etatSync.value);
    }
    if (schoolId.present) {
      map['school_id'] = Variable<int>(schoolId.value);
    }
    if (userId.present) {
      map['user_id'] = Variable<int>(userId.value);
    }
    if (type.present) {
      map['type'] = Variable<String>(type.value);
    }
    if (titre.present) {
      map['titre'] = Variable<String>(titre.value);
    }
    if (message.present) {
      map['message'] = Variable<String>(message.value);
    }
    if (lien.present) {
      map['lien'] = Variable<String>(lien.value);
    }
    if (lu.present) {
      map['lu'] = Variable<bool>(lu.value);
    }
    if (luLe.present) {
      map['lu_le'] = Variable<String>(luLe.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('NotificationsInternesCompanion(')
          ..write('id: $id, ')
          ..write('etatSync: $etatSync, ')
          ..write('schoolId: $schoolId, ')
          ..write('userId: $userId, ')
          ..write('type: $type, ')
          ..write('titre: $titre, ')
          ..write('message: $message, ')
          ..write('lien: $lien, ')
          ..write('lu: $lu, ')
          ..write('luLe: $luLe')
          ..write(')'))
        .toString();
  }
}

class $OutboxOperationsTable extends OutboxOperations
    with TableInfo<$OutboxOperationsTable, OutboxOperation> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $OutboxOperationsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _methodeMeta = const VerificationMeta(
    'methode',
  );
  @override
  late final GeneratedColumn<String> methode = GeneratedColumn<String>(
    'methode',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _cheminMeta = const VerificationMeta('chemin');
  @override
  late final GeneratedColumn<String> chemin = GeneratedColumn<String>(
    'chemin',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _corpsMeta = const VerificationMeta('corps');
  @override
  late final GeneratedColumn<String> corps = GeneratedColumn<String>(
    'corps',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _entiteMeta = const VerificationMeta('entite');
  @override
  late final GeneratedColumn<String> entite = GeneratedColumn<String>(
    'entite',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _entiteIdMeta = const VerificationMeta(
    'entiteId',
  );
  @override
  late final GeneratedColumn<int> entiteId = GeneratedColumn<int>(
    'entite_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _tentativesMeta = const VerificationMeta(
    'tentatives',
  );
  @override
  late final GeneratedColumn<int> tentatives = GeneratedColumn<int>(
    'tentatives',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _derniereErreurMeta = const VerificationMeta(
    'derniereErreur',
  );
  @override
  late final GeneratedColumn<String> derniereErreur = GeneratedColumn<String>(
    'derniere_erreur',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _creeLeMeta = const VerificationMeta('creeLe');
  @override
  late final GeneratedColumn<DateTime> creeLe = GeneratedColumn<DateTime>(
    'cree_le',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _prochainEssaiMeta = const VerificationMeta(
    'prochainEssai',
  );
  @override
  late final GeneratedColumn<DateTime> prochainEssai =
      GeneratedColumn<DateTime>(
        'prochain_essai',
        aliasedName,
        true,
        type: DriftSqlType.dateTime,
        requiredDuringInsert: false,
      );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    methode,
    chemin,
    corps,
    entite,
    entiteId,
    tentatives,
    derniereErreur,
    creeLe,
    prochainEssai,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'outbox_operations';
  @override
  VerificationContext validateIntegrity(
    Insertable<OutboxOperation> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('methode')) {
      context.handle(
        _methodeMeta,
        methode.isAcceptableOrUnknown(data['methode']!, _methodeMeta),
      );
    } else if (isInserting) {
      context.missing(_methodeMeta);
    }
    if (data.containsKey('chemin')) {
      context.handle(
        _cheminMeta,
        chemin.isAcceptableOrUnknown(data['chemin']!, _cheminMeta),
      );
    } else if (isInserting) {
      context.missing(_cheminMeta);
    }
    if (data.containsKey('corps')) {
      context.handle(
        _corpsMeta,
        corps.isAcceptableOrUnknown(data['corps']!, _corpsMeta),
      );
    } else if (isInserting) {
      context.missing(_corpsMeta);
    }
    if (data.containsKey('entite')) {
      context.handle(
        _entiteMeta,
        entite.isAcceptableOrUnknown(data['entite']!, _entiteMeta),
      );
    }
    if (data.containsKey('entite_id')) {
      context.handle(
        _entiteIdMeta,
        entiteId.isAcceptableOrUnknown(data['entite_id']!, _entiteIdMeta),
      );
    }
    if (data.containsKey('tentatives')) {
      context.handle(
        _tentativesMeta,
        tentatives.isAcceptableOrUnknown(data['tentatives']!, _tentativesMeta),
      );
    }
    if (data.containsKey('derniere_erreur')) {
      context.handle(
        _derniereErreurMeta,
        derniereErreur.isAcceptableOrUnknown(
          data['derniere_erreur']!,
          _derniereErreurMeta,
        ),
      );
    }
    if (data.containsKey('cree_le')) {
      context.handle(
        _creeLeMeta,
        creeLe.isAcceptableOrUnknown(data['cree_le']!, _creeLeMeta),
      );
    } else if (isInserting) {
      context.missing(_creeLeMeta);
    }
    if (data.containsKey('prochain_essai')) {
      context.handle(
        _prochainEssaiMeta,
        prochainEssai.isAcceptableOrUnknown(
          data['prochain_essai']!,
          _prochainEssaiMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  OutboxOperation map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return OutboxOperation(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      methode: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}methode'],
      )!,
      chemin: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}chemin'],
      )!,
      corps: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}corps'],
      )!,
      entite: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}entite'],
      ),
      entiteId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}entite_id'],
      ),
      tentatives: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}tentatives'],
      )!,
      derniereErreur: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}derniere_erreur'],
      ),
      creeLe: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}cree_le'],
      )!,
      prochainEssai: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}prochain_essai'],
      ),
    );
  }

  @override
  $OutboxOperationsTable createAlias(String alias) {
    return $OutboxOperationsTable(attachedDatabase, alias);
  }
}

class OutboxOperation extends DataClass implements Insertable<OutboxOperation> {
  final String id;
  final String methode;
  final String chemin;
  final String corps;
  final String? entite;
  final int? entiteId;
  final int tentatives;
  final String? derniereErreur;
  final DateTime creeLe;

  /// Report du prochain essai — porte le back-off exponentiel.
  final DateTime? prochainEssai;
  const OutboxOperation({
    required this.id,
    required this.methode,
    required this.chemin,
    required this.corps,
    this.entite,
    this.entiteId,
    required this.tentatives,
    this.derniereErreur,
    required this.creeLe,
    this.prochainEssai,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['methode'] = Variable<String>(methode);
    map['chemin'] = Variable<String>(chemin);
    map['corps'] = Variable<String>(corps);
    if (!nullToAbsent || entite != null) {
      map['entite'] = Variable<String>(entite);
    }
    if (!nullToAbsent || entiteId != null) {
      map['entite_id'] = Variable<int>(entiteId);
    }
    map['tentatives'] = Variable<int>(tentatives);
    if (!nullToAbsent || derniereErreur != null) {
      map['derniere_erreur'] = Variable<String>(derniereErreur);
    }
    map['cree_le'] = Variable<DateTime>(creeLe);
    if (!nullToAbsent || prochainEssai != null) {
      map['prochain_essai'] = Variable<DateTime>(prochainEssai);
    }
    return map;
  }

  OutboxOperationsCompanion toCompanion(bool nullToAbsent) {
    return OutboxOperationsCompanion(
      id: Value(id),
      methode: Value(methode),
      chemin: Value(chemin),
      corps: Value(corps),
      entite: entite == null && nullToAbsent
          ? const Value.absent()
          : Value(entite),
      entiteId: entiteId == null && nullToAbsent
          ? const Value.absent()
          : Value(entiteId),
      tentatives: Value(tentatives),
      derniereErreur: derniereErreur == null && nullToAbsent
          ? const Value.absent()
          : Value(derniereErreur),
      creeLe: Value(creeLe),
      prochainEssai: prochainEssai == null && nullToAbsent
          ? const Value.absent()
          : Value(prochainEssai),
    );
  }

  factory OutboxOperation.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return OutboxOperation(
      id: serializer.fromJson<String>(json['id']),
      methode: serializer.fromJson<String>(json['methode']),
      chemin: serializer.fromJson<String>(json['chemin']),
      corps: serializer.fromJson<String>(json['corps']),
      entite: serializer.fromJson<String?>(json['entite']),
      entiteId: serializer.fromJson<int?>(json['entiteId']),
      tentatives: serializer.fromJson<int>(json['tentatives']),
      derniereErreur: serializer.fromJson<String?>(json['derniereErreur']),
      creeLe: serializer.fromJson<DateTime>(json['creeLe']),
      prochainEssai: serializer.fromJson<DateTime?>(json['prochainEssai']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'methode': serializer.toJson<String>(methode),
      'chemin': serializer.toJson<String>(chemin),
      'corps': serializer.toJson<String>(corps),
      'entite': serializer.toJson<String?>(entite),
      'entiteId': serializer.toJson<int?>(entiteId),
      'tentatives': serializer.toJson<int>(tentatives),
      'derniereErreur': serializer.toJson<String?>(derniereErreur),
      'creeLe': serializer.toJson<DateTime>(creeLe),
      'prochainEssai': serializer.toJson<DateTime?>(prochainEssai),
    };
  }

  OutboxOperation copyWith({
    String? id,
    String? methode,
    String? chemin,
    String? corps,
    Value<String?> entite = const Value.absent(),
    Value<int?> entiteId = const Value.absent(),
    int? tentatives,
    Value<String?> derniereErreur = const Value.absent(),
    DateTime? creeLe,
    Value<DateTime?> prochainEssai = const Value.absent(),
  }) => OutboxOperation(
    id: id ?? this.id,
    methode: methode ?? this.methode,
    chemin: chemin ?? this.chemin,
    corps: corps ?? this.corps,
    entite: entite.present ? entite.value : this.entite,
    entiteId: entiteId.present ? entiteId.value : this.entiteId,
    tentatives: tentatives ?? this.tentatives,
    derniereErreur: derniereErreur.present
        ? derniereErreur.value
        : this.derniereErreur,
    creeLe: creeLe ?? this.creeLe,
    prochainEssai: prochainEssai.present
        ? prochainEssai.value
        : this.prochainEssai,
  );
  OutboxOperation copyWithCompanion(OutboxOperationsCompanion data) {
    return OutboxOperation(
      id: data.id.present ? data.id.value : this.id,
      methode: data.methode.present ? data.methode.value : this.methode,
      chemin: data.chemin.present ? data.chemin.value : this.chemin,
      corps: data.corps.present ? data.corps.value : this.corps,
      entite: data.entite.present ? data.entite.value : this.entite,
      entiteId: data.entiteId.present ? data.entiteId.value : this.entiteId,
      tentatives: data.tentatives.present
          ? data.tentatives.value
          : this.tentatives,
      derniereErreur: data.derniereErreur.present
          ? data.derniereErreur.value
          : this.derniereErreur,
      creeLe: data.creeLe.present ? data.creeLe.value : this.creeLe,
      prochainEssai: data.prochainEssai.present
          ? data.prochainEssai.value
          : this.prochainEssai,
    );
  }

  @override
  String toString() {
    return (StringBuffer('OutboxOperation(')
          ..write('id: $id, ')
          ..write('methode: $methode, ')
          ..write('chemin: $chemin, ')
          ..write('corps: $corps, ')
          ..write('entite: $entite, ')
          ..write('entiteId: $entiteId, ')
          ..write('tentatives: $tentatives, ')
          ..write('derniereErreur: $derniereErreur, ')
          ..write('creeLe: $creeLe, ')
          ..write('prochainEssai: $prochainEssai')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    methode,
    chemin,
    corps,
    entite,
    entiteId,
    tentatives,
    derniereErreur,
    creeLe,
    prochainEssai,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutboxOperation &&
          other.id == this.id &&
          other.methode == this.methode &&
          other.chemin == this.chemin &&
          other.corps == this.corps &&
          other.entite == this.entite &&
          other.entiteId == this.entiteId &&
          other.tentatives == this.tentatives &&
          other.derniereErreur == this.derniereErreur &&
          other.creeLe == this.creeLe &&
          other.prochainEssai == this.prochainEssai);
}

class OutboxOperationsCompanion extends UpdateCompanion<OutboxOperation> {
  final Value<String> id;
  final Value<String> methode;
  final Value<String> chemin;
  final Value<String> corps;
  final Value<String?> entite;
  final Value<int?> entiteId;
  final Value<int> tentatives;
  final Value<String?> derniereErreur;
  final Value<DateTime> creeLe;
  final Value<DateTime?> prochainEssai;
  final Value<int> rowid;
  const OutboxOperationsCompanion({
    this.id = const Value.absent(),
    this.methode = const Value.absent(),
    this.chemin = const Value.absent(),
    this.corps = const Value.absent(),
    this.entite = const Value.absent(),
    this.entiteId = const Value.absent(),
    this.tentatives = const Value.absent(),
    this.derniereErreur = const Value.absent(),
    this.creeLe = const Value.absent(),
    this.prochainEssai = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  OutboxOperationsCompanion.insert({
    required String id,
    required String methode,
    required String chemin,
    required String corps,
    this.entite = const Value.absent(),
    this.entiteId = const Value.absent(),
    this.tentatives = const Value.absent(),
    this.derniereErreur = const Value.absent(),
    required DateTime creeLe,
    this.prochainEssai = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : id = Value(id),
       methode = Value(methode),
       chemin = Value(chemin),
       corps = Value(corps),
       creeLe = Value(creeLe);
  static Insertable<OutboxOperation> custom({
    Expression<String>? id,
    Expression<String>? methode,
    Expression<String>? chemin,
    Expression<String>? corps,
    Expression<String>? entite,
    Expression<int>? entiteId,
    Expression<int>? tentatives,
    Expression<String>? derniereErreur,
    Expression<DateTime>? creeLe,
    Expression<DateTime>? prochainEssai,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (methode != null) 'methode': methode,
      if (chemin != null) 'chemin': chemin,
      if (corps != null) 'corps': corps,
      if (entite != null) 'entite': entite,
      if (entiteId != null) 'entite_id': entiteId,
      if (tentatives != null) 'tentatives': tentatives,
      if (derniereErreur != null) 'derniere_erreur': derniereErreur,
      if (creeLe != null) 'cree_le': creeLe,
      if (prochainEssai != null) 'prochain_essai': prochainEssai,
      if (rowid != null) 'rowid': rowid,
    });
  }

  OutboxOperationsCompanion copyWith({
    Value<String>? id,
    Value<String>? methode,
    Value<String>? chemin,
    Value<String>? corps,
    Value<String?>? entite,
    Value<int?>? entiteId,
    Value<int>? tentatives,
    Value<String?>? derniereErreur,
    Value<DateTime>? creeLe,
    Value<DateTime?>? prochainEssai,
    Value<int>? rowid,
  }) {
    return OutboxOperationsCompanion(
      id: id ?? this.id,
      methode: methode ?? this.methode,
      chemin: chemin ?? this.chemin,
      corps: corps ?? this.corps,
      entite: entite ?? this.entite,
      entiteId: entiteId ?? this.entiteId,
      tentatives: tentatives ?? this.tentatives,
      derniereErreur: derniereErreur ?? this.derniereErreur,
      creeLe: creeLe ?? this.creeLe,
      prochainEssai: prochainEssai ?? this.prochainEssai,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (methode.present) {
      map['methode'] = Variable<String>(methode.value);
    }
    if (chemin.present) {
      map['chemin'] = Variable<String>(chemin.value);
    }
    if (corps.present) {
      map['corps'] = Variable<String>(corps.value);
    }
    if (entite.present) {
      map['entite'] = Variable<String>(entite.value);
    }
    if (entiteId.present) {
      map['entite_id'] = Variable<int>(entiteId.value);
    }
    if (tentatives.present) {
      map['tentatives'] = Variable<int>(tentatives.value);
    }
    if (derniereErreur.present) {
      map['derniere_erreur'] = Variable<String>(derniereErreur.value);
    }
    if (creeLe.present) {
      map['cree_le'] = Variable<DateTime>(creeLe.value);
    }
    if (prochainEssai.present) {
      map['prochain_essai'] = Variable<DateTime>(prochainEssai.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('OutboxOperationsCompanion(')
          ..write('id: $id, ')
          ..write('methode: $methode, ')
          ..write('chemin: $chemin, ')
          ..write('corps: $corps, ')
          ..write('entite: $entite, ')
          ..write('entiteId: $entiteId, ')
          ..write('tentatives: $tentatives, ')
          ..write('derniereErreur: $derniereErreur, ')
          ..write('creeLe: $creeLe, ')
          ..write('prochainEssai: $prochainEssai, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $SyncEtatTable extends SyncEtat
    with TableInfo<$SyncEtatTable, SyncEtatData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SyncEtatTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _cleMeta = const VerificationMeta('cle');
  @override
  late final GeneratedColumn<String> cle = GeneratedColumn<String>(
    'cle',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _valeurMeta = const VerificationMeta('valeur');
  @override
  late final GeneratedColumn<String> valeur = GeneratedColumn<String>(
    'valeur',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [cle, valeur];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'sync_etat';
  @override
  VerificationContext validateIntegrity(
    Insertable<SyncEtatData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('cle')) {
      context.handle(
        _cleMeta,
        cle.isAcceptableOrUnknown(data['cle']!, _cleMeta),
      );
    } else if (isInserting) {
      context.missing(_cleMeta);
    }
    if (data.containsKey('valeur')) {
      context.handle(
        _valeurMeta,
        valeur.isAcceptableOrUnknown(data['valeur']!, _valeurMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {cle};
  @override
  SyncEtatData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return SyncEtatData(
      cle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cle'],
      )!,
      valeur: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}valeur'],
      ),
    );
  }

  @override
  $SyncEtatTable createAlias(String alias) {
    return $SyncEtatTable(attachedDatabase, alias);
  }
}

class SyncEtatData extends DataClass implements Insertable<SyncEtatData> {
  final String cle;
  final String? valeur;
  const SyncEtatData({required this.cle, this.valeur});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['cle'] = Variable<String>(cle);
    if (!nullToAbsent || valeur != null) {
      map['valeur'] = Variable<String>(valeur);
    }
    return map;
  }

  SyncEtatCompanion toCompanion(bool nullToAbsent) {
    return SyncEtatCompanion(
      cle: Value(cle),
      valeur: valeur == null && nullToAbsent
          ? const Value.absent()
          : Value(valeur),
    );
  }

  factory SyncEtatData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return SyncEtatData(
      cle: serializer.fromJson<String>(json['cle']),
      valeur: serializer.fromJson<String?>(json['valeur']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'cle': serializer.toJson<String>(cle),
      'valeur': serializer.toJson<String?>(valeur),
    };
  }

  SyncEtatData copyWith({
    String? cle,
    Value<String?> valeur = const Value.absent(),
  }) => SyncEtatData(
    cle: cle ?? this.cle,
    valeur: valeur.present ? valeur.value : this.valeur,
  );
  SyncEtatData copyWithCompanion(SyncEtatCompanion data) {
    return SyncEtatData(
      cle: data.cle.present ? data.cle.value : this.cle,
      valeur: data.valeur.present ? data.valeur.value : this.valeur,
    );
  }

  @override
  String toString() {
    return (StringBuffer('SyncEtatData(')
          ..write('cle: $cle, ')
          ..write('valeur: $valeur')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(cle, valeur);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is SyncEtatData &&
          other.cle == this.cle &&
          other.valeur == this.valeur);
}

class SyncEtatCompanion extends UpdateCompanion<SyncEtatData> {
  final Value<String> cle;
  final Value<String?> valeur;
  final Value<int> rowid;
  const SyncEtatCompanion({
    this.cle = const Value.absent(),
    this.valeur = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  SyncEtatCompanion.insert({
    required String cle,
    this.valeur = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : cle = Value(cle);
  static Insertable<SyncEtatData> custom({
    Expression<String>? cle,
    Expression<String>? valeur,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (cle != null) 'cle': cle,
      if (valeur != null) 'valeur': valeur,
      if (rowid != null) 'rowid': rowid,
    });
  }

  SyncEtatCompanion copyWith({
    Value<String>? cle,
    Value<String?>? valeur,
    Value<int>? rowid,
  }) {
    return SyncEtatCompanion(
      cle: cle ?? this.cle,
      valeur: valeur ?? this.valeur,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (cle.present) {
      map['cle'] = Variable<String>(cle.value);
    }
    if (valeur.present) {
      map['valeur'] = Variable<String>(valeur.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SyncEtatCompanion(')
          ..write('cle: $cle, ')
          ..write('valeur: $valeur, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $AnneeScolairesTable anneeScolaires = $AnneeScolairesTable(this);
  late final $TrimestresTable trimestres = $TrimestresTable(this);
  late final $SequencesTable sequences = $SequencesTable(this);
  late final $NiveauxTable niveaux = $NiveauxTable(this);
  late final $MatieresTable matieres = $MatieresTable(this);
  late final $ClassesTable classes = $ClassesTable(this);
  late final $ClasseMatieresTable classeMatieres = $ClasseMatieresTable(this);
  late final $EmploisDuTempsTable emploisDuTemps = $EmploisDuTempsTable(this);
  late final $ProgressionItemsTable progressionItems = $ProgressionItemsTable(
    this,
  );
  late final $ElevesTable eleves = $ElevesTable(this);
  late final $PersonnelsTable personnels = $PersonnelsTable(this);
  late final $SeancesTable seances = $SeancesTable(this);
  late final $PresencesTable presences = $PresencesTable(this);
  late final $NotesTable notes = $NotesTable(this);
  late final $SanctionsTable sanctions = $SanctionsTable(this);
  late final $AnnoncesTable annonces = $AnnoncesTable(this);
  late final $NotificationsInternesTable notificationsInternes =
      $NotificationsInternesTable(this);
  late final $OutboxOperationsTable outboxOperations = $OutboxOperationsTable(
    this,
  );
  late final $SyncEtatTable syncEtat = $SyncEtatTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    anneeScolaires,
    trimestres,
    sequences,
    niveaux,
    matieres,
    classes,
    classeMatieres,
    emploisDuTemps,
    progressionItems,
    eleves,
    personnels,
    seances,
    presences,
    notes,
    sanctions,
    annonces,
    notificationsInternes,
    outboxOperations,
    syncEtat,
  ];
}

typedef $$AnneeScolairesTableCreateCompanionBuilder =
    AnneeScolairesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      required String libelle,
      Value<String?> dateDebut,
      Value<String?> dateFin,
      Value<bool> isActive,
    });
typedef $$AnneeScolairesTableUpdateCompanionBuilder =
    AnneeScolairesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<String> libelle,
      Value<String?> dateDebut,
      Value<String?> dateFin,
      Value<bool> isActive,
    });

class $$AnneeScolairesTableFilterComposer
    extends Composer<_$AppDatabase, $AnneeScolairesTable> {
  $$AnneeScolairesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get libelle => $composableBuilder(
    column: $table.libelle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateDebut => $composableBuilder(
    column: $table.dateDebut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateFin => $composableBuilder(
    column: $table.dateFin,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get isActive => $composableBuilder(
    column: $table.isActive,
    builder: (column) => ColumnFilters(column),
  );
}

class $$AnneeScolairesTableOrderingComposer
    extends Composer<_$AppDatabase, $AnneeScolairesTable> {
  $$AnneeScolairesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get libelle => $composableBuilder(
    column: $table.libelle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateDebut => $composableBuilder(
    column: $table.dateDebut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateFin => $composableBuilder(
    column: $table.dateFin,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get isActive => $composableBuilder(
    column: $table.isActive,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$AnneeScolairesTableAnnotationComposer
    extends Composer<_$AppDatabase, $AnneeScolairesTable> {
  $$AnneeScolairesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<String> get libelle =>
      $composableBuilder(column: $table.libelle, builder: (column) => column);

  GeneratedColumn<String> get dateDebut =>
      $composableBuilder(column: $table.dateDebut, builder: (column) => column);

  GeneratedColumn<String> get dateFin =>
      $composableBuilder(column: $table.dateFin, builder: (column) => column);

  GeneratedColumn<bool> get isActive =>
      $composableBuilder(column: $table.isActive, builder: (column) => column);
}

class $$AnneeScolairesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $AnneeScolairesTable,
          AnneeScolaire,
          $$AnneeScolairesTableFilterComposer,
          $$AnneeScolairesTableOrderingComposer,
          $$AnneeScolairesTableAnnotationComposer,
          $$AnneeScolairesTableCreateCompanionBuilder,
          $$AnneeScolairesTableUpdateCompanionBuilder,
          (
            AnneeScolaire,
            BaseReferences<_$AppDatabase, $AnneeScolairesTable, AnneeScolaire>,
          ),
          AnneeScolaire,
          PrefetchHooks Function()
        > {
  $$AnneeScolairesTableTableManager(
    _$AppDatabase db,
    $AnneeScolairesTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$AnneeScolairesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$AnneeScolairesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$AnneeScolairesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<String> libelle = const Value.absent(),
                Value<String?> dateDebut = const Value.absent(),
                Value<String?> dateFin = const Value.absent(),
                Value<bool> isActive = const Value.absent(),
              }) => AnneeScolairesCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                libelle: libelle,
                dateDebut: dateDebut,
                dateFin: dateFin,
                isActive: isActive,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                required String libelle,
                Value<String?> dateDebut = const Value.absent(),
                Value<String?> dateFin = const Value.absent(),
                Value<bool> isActive = const Value.absent(),
              }) => AnneeScolairesCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                libelle: libelle,
                dateDebut: dateDebut,
                dateFin: dateFin,
                isActive: isActive,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$AnneeScolairesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $AnneeScolairesTable,
      AnneeScolaire,
      $$AnneeScolairesTableFilterComposer,
      $$AnneeScolairesTableOrderingComposer,
      $$AnneeScolairesTableAnnotationComposer,
      $$AnneeScolairesTableCreateCompanionBuilder,
      $$AnneeScolairesTableUpdateCompanionBuilder,
      (
        AnneeScolaire,
        BaseReferences<_$AppDatabase, $AnneeScolairesTable, AnneeScolaire>,
      ),
      AnneeScolaire,
      PrefetchHooks Function()
    >;
typedef $$TrimestresTableCreateCompanionBuilder =
    TrimestresCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int anneeScolaireId,
      required String libelle,
      Value<int> ordre,
      Value<String?> dateDebut,
      Value<String?> dateFin,
      Value<bool> isActive,
    });
typedef $$TrimestresTableUpdateCompanionBuilder =
    TrimestresCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> anneeScolaireId,
      Value<String> libelle,
      Value<int> ordre,
      Value<String?> dateDebut,
      Value<String?> dateFin,
      Value<bool> isActive,
    });

class $$TrimestresTableFilterComposer
    extends Composer<_$AppDatabase, $TrimestresTable> {
  $$TrimestresTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get anneeScolaireId => $composableBuilder(
    column: $table.anneeScolaireId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get libelle => $composableBuilder(
    column: $table.libelle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateDebut => $composableBuilder(
    column: $table.dateDebut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateFin => $composableBuilder(
    column: $table.dateFin,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get isActive => $composableBuilder(
    column: $table.isActive,
    builder: (column) => ColumnFilters(column),
  );
}

class $$TrimestresTableOrderingComposer
    extends Composer<_$AppDatabase, $TrimestresTable> {
  $$TrimestresTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get anneeScolaireId => $composableBuilder(
    column: $table.anneeScolaireId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get libelle => $composableBuilder(
    column: $table.libelle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateDebut => $composableBuilder(
    column: $table.dateDebut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateFin => $composableBuilder(
    column: $table.dateFin,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get isActive => $composableBuilder(
    column: $table.isActive,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$TrimestresTableAnnotationComposer
    extends Composer<_$AppDatabase, $TrimestresTable> {
  $$TrimestresTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get anneeScolaireId => $composableBuilder(
    column: $table.anneeScolaireId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get libelle =>
      $composableBuilder(column: $table.libelle, builder: (column) => column);

  GeneratedColumn<int> get ordre =>
      $composableBuilder(column: $table.ordre, builder: (column) => column);

  GeneratedColumn<String> get dateDebut =>
      $composableBuilder(column: $table.dateDebut, builder: (column) => column);

  GeneratedColumn<String> get dateFin =>
      $composableBuilder(column: $table.dateFin, builder: (column) => column);

  GeneratedColumn<bool> get isActive =>
      $composableBuilder(column: $table.isActive, builder: (column) => column);
}

class $$TrimestresTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $TrimestresTable,
          Trimestre,
          $$TrimestresTableFilterComposer,
          $$TrimestresTableOrderingComposer,
          $$TrimestresTableAnnotationComposer,
          $$TrimestresTableCreateCompanionBuilder,
          $$TrimestresTableUpdateCompanionBuilder,
          (
            Trimestre,
            BaseReferences<_$AppDatabase, $TrimestresTable, Trimestre>,
          ),
          Trimestre,
          PrefetchHooks Function()
        > {
  $$TrimestresTableTableManager(_$AppDatabase db, $TrimestresTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$TrimestresTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$TrimestresTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$TrimestresTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> anneeScolaireId = const Value.absent(),
                Value<String> libelle = const Value.absent(),
                Value<int> ordre = const Value.absent(),
                Value<String?> dateDebut = const Value.absent(),
                Value<String?> dateFin = const Value.absent(),
                Value<bool> isActive = const Value.absent(),
              }) => TrimestresCompanion(
                id: id,
                etatSync: etatSync,
                anneeScolaireId: anneeScolaireId,
                libelle: libelle,
                ordre: ordre,
                dateDebut: dateDebut,
                dateFin: dateFin,
                isActive: isActive,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int anneeScolaireId,
                required String libelle,
                Value<int> ordre = const Value.absent(),
                Value<String?> dateDebut = const Value.absent(),
                Value<String?> dateFin = const Value.absent(),
                Value<bool> isActive = const Value.absent(),
              }) => TrimestresCompanion.insert(
                id: id,
                etatSync: etatSync,
                anneeScolaireId: anneeScolaireId,
                libelle: libelle,
                ordre: ordre,
                dateDebut: dateDebut,
                dateFin: dateFin,
                isActive: isActive,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$TrimestresTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $TrimestresTable,
      Trimestre,
      $$TrimestresTableFilterComposer,
      $$TrimestresTableOrderingComposer,
      $$TrimestresTableAnnotationComposer,
      $$TrimestresTableCreateCompanionBuilder,
      $$TrimestresTableUpdateCompanionBuilder,
      (Trimestre, BaseReferences<_$AppDatabase, $TrimestresTable, Trimestre>),
      Trimestre,
      PrefetchHooks Function()
    >;
typedef $$SequencesTableCreateCompanionBuilder =
    SequencesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int trimestreId,
      Value<int> ordre,
      required String libelle,
    });
typedef $$SequencesTableUpdateCompanionBuilder =
    SequencesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> trimestreId,
      Value<int> ordre,
      Value<String> libelle,
    });

class $$SequencesTableFilterComposer
    extends Composer<_$AppDatabase, $SequencesTable> {
  $$SequencesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get libelle => $composableBuilder(
    column: $table.libelle,
    builder: (column) => ColumnFilters(column),
  );
}

class $$SequencesTableOrderingComposer
    extends Composer<_$AppDatabase, $SequencesTable> {
  $$SequencesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get libelle => $composableBuilder(
    column: $table.libelle,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$SequencesTableAnnotationComposer
    extends Composer<_$AppDatabase, $SequencesTable> {
  $$SequencesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get ordre =>
      $composableBuilder(column: $table.ordre, builder: (column) => column);

  GeneratedColumn<String> get libelle =>
      $composableBuilder(column: $table.libelle, builder: (column) => column);
}

class $$SequencesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $SequencesTable,
          Sequence,
          $$SequencesTableFilterComposer,
          $$SequencesTableOrderingComposer,
          $$SequencesTableAnnotationComposer,
          $$SequencesTableCreateCompanionBuilder,
          $$SequencesTableUpdateCompanionBuilder,
          (Sequence, BaseReferences<_$AppDatabase, $SequencesTable, Sequence>),
          Sequence,
          PrefetchHooks Function()
        > {
  $$SequencesTableTableManager(_$AppDatabase db, $SequencesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SequencesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SequencesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SequencesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> trimestreId = const Value.absent(),
                Value<int> ordre = const Value.absent(),
                Value<String> libelle = const Value.absent(),
              }) => SequencesCompanion(
                id: id,
                etatSync: etatSync,
                trimestreId: trimestreId,
                ordre: ordre,
                libelle: libelle,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int trimestreId,
                Value<int> ordre = const Value.absent(),
                required String libelle,
              }) => SequencesCompanion.insert(
                id: id,
                etatSync: etatSync,
                trimestreId: trimestreId,
                ordre: ordre,
                libelle: libelle,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$SequencesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $SequencesTable,
      Sequence,
      $$SequencesTableFilterComposer,
      $$SequencesTableOrderingComposer,
      $$SequencesTableAnnotationComposer,
      $$SequencesTableCreateCompanionBuilder,
      $$SequencesTableUpdateCompanionBuilder,
      (Sequence, BaseReferences<_$AppDatabase, $SequencesTable, Sequence>),
      Sequence,
      PrefetchHooks Function()
    >;
typedef $$NiveauxTableCreateCompanionBuilder =
    NiveauxCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<String?> code,
      Value<String?> nameFr,
      Value<String?> nameEn,
      Value<int?> sousSystemId,
      Value<int?> schoolId,
      Value<int> ordre,
    });
typedef $$NiveauxTableUpdateCompanionBuilder =
    NiveauxCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<String?> code,
      Value<String?> nameFr,
      Value<String?> nameEn,
      Value<int?> sousSystemId,
      Value<int?> schoolId,
      Value<int> ordre,
    });

class $$NiveauxTableFilterComposer
    extends Composer<_$AppDatabase, $NiveauxTable> {
  $$NiveauxTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get code => $composableBuilder(
    column: $table.code,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nameFr => $composableBuilder(
    column: $table.nameFr,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nameEn => $composableBuilder(
    column: $table.nameEn,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get sousSystemId => $composableBuilder(
    column: $table.sousSystemId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnFilters(column),
  );
}

class $$NiveauxTableOrderingComposer
    extends Composer<_$AppDatabase, $NiveauxTable> {
  $$NiveauxTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get code => $composableBuilder(
    column: $table.code,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nameFr => $composableBuilder(
    column: $table.nameFr,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nameEn => $composableBuilder(
    column: $table.nameEn,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get sousSystemId => $composableBuilder(
    column: $table.sousSystemId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$NiveauxTableAnnotationComposer
    extends Composer<_$AppDatabase, $NiveauxTable> {
  $$NiveauxTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<String> get code =>
      $composableBuilder(column: $table.code, builder: (column) => column);

  GeneratedColumn<String> get nameFr =>
      $composableBuilder(column: $table.nameFr, builder: (column) => column);

  GeneratedColumn<String> get nameEn =>
      $composableBuilder(column: $table.nameEn, builder: (column) => column);

  GeneratedColumn<int> get sousSystemId => $composableBuilder(
    column: $table.sousSystemId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get ordre =>
      $composableBuilder(column: $table.ordre, builder: (column) => column);
}

class $$NiveauxTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $NiveauxTable,
          NiveauxData,
          $$NiveauxTableFilterComposer,
          $$NiveauxTableOrderingComposer,
          $$NiveauxTableAnnotationComposer,
          $$NiveauxTableCreateCompanionBuilder,
          $$NiveauxTableUpdateCompanionBuilder,
          (
            NiveauxData,
            BaseReferences<_$AppDatabase, $NiveauxTable, NiveauxData>,
          ),
          NiveauxData,
          PrefetchHooks Function()
        > {
  $$NiveauxTableTableManager(_$AppDatabase db, $NiveauxTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$NiveauxTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$NiveauxTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$NiveauxTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<String?> code = const Value.absent(),
                Value<String?> nameFr = const Value.absent(),
                Value<String?> nameEn = const Value.absent(),
                Value<int?> sousSystemId = const Value.absent(),
                Value<int?> schoolId = const Value.absent(),
                Value<int> ordre = const Value.absent(),
              }) => NiveauxCompanion(
                id: id,
                etatSync: etatSync,
                code: code,
                nameFr: nameFr,
                nameEn: nameEn,
                sousSystemId: sousSystemId,
                schoolId: schoolId,
                ordre: ordre,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<String?> code = const Value.absent(),
                Value<String?> nameFr = const Value.absent(),
                Value<String?> nameEn = const Value.absent(),
                Value<int?> sousSystemId = const Value.absent(),
                Value<int?> schoolId = const Value.absent(),
                Value<int> ordre = const Value.absent(),
              }) => NiveauxCompanion.insert(
                id: id,
                etatSync: etatSync,
                code: code,
                nameFr: nameFr,
                nameEn: nameEn,
                sousSystemId: sousSystemId,
                schoolId: schoolId,
                ordre: ordre,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$NiveauxTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $NiveauxTable,
      NiveauxData,
      $$NiveauxTableFilterComposer,
      $$NiveauxTableOrderingComposer,
      $$NiveauxTableAnnotationComposer,
      $$NiveauxTableCreateCompanionBuilder,
      $$NiveauxTableUpdateCompanionBuilder,
      (NiveauxData, BaseReferences<_$AppDatabase, $NiveauxTable, NiveauxData>),
      NiveauxData,
      PrefetchHooks Function()
    >;
typedef $$MatieresTableCreateCompanionBuilder =
    MatieresCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      Value<int?> departementId,
      required String nom,
      Value<String?> nomEn,
      Value<String?> abbreviation,
      Value<int?> notation,
      Value<bool> evaluePratique,
      Value<String?> repartitionVolets,
      Value<String?> statut,
    });
typedef $$MatieresTableUpdateCompanionBuilder =
    MatieresCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<int?> departementId,
      Value<String> nom,
      Value<String?> nomEn,
      Value<String?> abbreviation,
      Value<int?> notation,
      Value<bool> evaluePratique,
      Value<String?> repartitionVolets,
      Value<String?> statut,
    });

class $$MatieresTableFilterComposer
    extends Composer<_$AppDatabase, $MatieresTable> {
  $$MatieresTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get departementId => $composableBuilder(
    column: $table.departementId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nom => $composableBuilder(
    column: $table.nom,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nomEn => $composableBuilder(
    column: $table.nomEn,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get abbreviation => $composableBuilder(
    column: $table.abbreviation,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get notation => $composableBuilder(
    column: $table.notation,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get evaluePratique => $composableBuilder(
    column: $table.evaluePratique,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get repartitionVolets => $composableBuilder(
    column: $table.repartitionVolets,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnFilters(column),
  );
}

class $$MatieresTableOrderingComposer
    extends Composer<_$AppDatabase, $MatieresTable> {
  $$MatieresTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get departementId => $composableBuilder(
    column: $table.departementId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nom => $composableBuilder(
    column: $table.nom,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nomEn => $composableBuilder(
    column: $table.nomEn,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get abbreviation => $composableBuilder(
    column: $table.abbreviation,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get notation => $composableBuilder(
    column: $table.notation,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get evaluePratique => $composableBuilder(
    column: $table.evaluePratique,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get repartitionVolets => $composableBuilder(
    column: $table.repartitionVolets,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$MatieresTableAnnotationComposer
    extends Composer<_$AppDatabase, $MatieresTable> {
  $$MatieresTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get departementId => $composableBuilder(
    column: $table.departementId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nom =>
      $composableBuilder(column: $table.nom, builder: (column) => column);

  GeneratedColumn<String> get nomEn =>
      $composableBuilder(column: $table.nomEn, builder: (column) => column);

  GeneratedColumn<String> get abbreviation => $composableBuilder(
    column: $table.abbreviation,
    builder: (column) => column,
  );

  GeneratedColumn<int> get notation =>
      $composableBuilder(column: $table.notation, builder: (column) => column);

  GeneratedColumn<bool> get evaluePratique => $composableBuilder(
    column: $table.evaluePratique,
    builder: (column) => column,
  );

  GeneratedColumn<String> get repartitionVolets => $composableBuilder(
    column: $table.repartitionVolets,
    builder: (column) => column,
  );

  GeneratedColumn<String> get statut =>
      $composableBuilder(column: $table.statut, builder: (column) => column);
}

class $$MatieresTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $MatieresTable,
          Matiere,
          $$MatieresTableFilterComposer,
          $$MatieresTableOrderingComposer,
          $$MatieresTableAnnotationComposer,
          $$MatieresTableCreateCompanionBuilder,
          $$MatieresTableUpdateCompanionBuilder,
          (Matiere, BaseReferences<_$AppDatabase, $MatieresTable, Matiere>),
          Matiere,
          PrefetchHooks Function()
        > {
  $$MatieresTableTableManager(_$AppDatabase db, $MatieresTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$MatieresTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$MatieresTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$MatieresTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<int?> departementId = const Value.absent(),
                Value<String> nom = const Value.absent(),
                Value<String?> nomEn = const Value.absent(),
                Value<String?> abbreviation = const Value.absent(),
                Value<int?> notation = const Value.absent(),
                Value<bool> evaluePratique = const Value.absent(),
                Value<String?> repartitionVolets = const Value.absent(),
                Value<String?> statut = const Value.absent(),
              }) => MatieresCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                departementId: departementId,
                nom: nom,
                nomEn: nomEn,
                abbreviation: abbreviation,
                notation: notation,
                evaluePratique: evaluePratique,
                repartitionVolets: repartitionVolets,
                statut: statut,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                Value<int?> departementId = const Value.absent(),
                required String nom,
                Value<String?> nomEn = const Value.absent(),
                Value<String?> abbreviation = const Value.absent(),
                Value<int?> notation = const Value.absent(),
                Value<bool> evaluePratique = const Value.absent(),
                Value<String?> repartitionVolets = const Value.absent(),
                Value<String?> statut = const Value.absent(),
              }) => MatieresCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                departementId: departementId,
                nom: nom,
                nomEn: nomEn,
                abbreviation: abbreviation,
                notation: notation,
                evaluePratique: evaluePratique,
                repartitionVolets: repartitionVolets,
                statut: statut,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$MatieresTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $MatieresTable,
      Matiere,
      $$MatieresTableFilterComposer,
      $$MatieresTableOrderingComposer,
      $$MatieresTableAnnotationComposer,
      $$MatieresTableCreateCompanionBuilder,
      $$MatieresTableUpdateCompanionBuilder,
      (Matiere, BaseReferences<_$AppDatabase, $MatieresTable, Matiere>),
      Matiere,
      PrefetchHooks Function()
    >;
typedef $$ClassesTableCreateCompanionBuilder =
    ClassesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      Value<int?> niveauId,
      Value<int?> niveauScolaireId,
      Value<int?> anneeScolaireId,
      Value<int?> professeurPrincipalId,
      Value<int?> titulaireId,
      Value<int?> surveillantGeneralId,
      required String nom,
      Value<String?> sigle,
      Value<int?> sousSystemeId,
      Value<String?> niveauClasse,
      Value<String?> filiere,
      Value<int?> capacite,
      Value<String?> qrToken,
    });
typedef $$ClassesTableUpdateCompanionBuilder =
    ClassesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<int?> niveauId,
      Value<int?> niveauScolaireId,
      Value<int?> anneeScolaireId,
      Value<int?> professeurPrincipalId,
      Value<int?> titulaireId,
      Value<int?> surveillantGeneralId,
      Value<String> nom,
      Value<String?> sigle,
      Value<int?> sousSystemeId,
      Value<String?> niveauClasse,
      Value<String?> filiere,
      Value<int?> capacite,
      Value<String?> qrToken,
    });

class $$ClassesTableFilterComposer
    extends Composer<_$AppDatabase, $ClassesTable> {
  $$ClassesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get niveauId => $composableBuilder(
    column: $table.niveauId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get niveauScolaireId => $composableBuilder(
    column: $table.niveauScolaireId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get anneeScolaireId => $composableBuilder(
    column: $table.anneeScolaireId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get professeurPrincipalId => $composableBuilder(
    column: $table.professeurPrincipalId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get titulaireId => $composableBuilder(
    column: $table.titulaireId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get surveillantGeneralId => $composableBuilder(
    column: $table.surveillantGeneralId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nom => $composableBuilder(
    column: $table.nom,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sigle => $composableBuilder(
    column: $table.sigle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get sousSystemeId => $composableBuilder(
    column: $table.sousSystemeId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get niveauClasse => $composableBuilder(
    column: $table.niveauClasse,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get filiere => $composableBuilder(
    column: $table.filiere,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get capacite => $composableBuilder(
    column: $table.capacite,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get qrToken => $composableBuilder(
    column: $table.qrToken,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ClassesTableOrderingComposer
    extends Composer<_$AppDatabase, $ClassesTable> {
  $$ClassesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get niveauId => $composableBuilder(
    column: $table.niveauId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get niveauScolaireId => $composableBuilder(
    column: $table.niveauScolaireId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get anneeScolaireId => $composableBuilder(
    column: $table.anneeScolaireId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get professeurPrincipalId => $composableBuilder(
    column: $table.professeurPrincipalId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get titulaireId => $composableBuilder(
    column: $table.titulaireId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get surveillantGeneralId => $composableBuilder(
    column: $table.surveillantGeneralId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nom => $composableBuilder(
    column: $table.nom,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sigle => $composableBuilder(
    column: $table.sigle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get sousSystemeId => $composableBuilder(
    column: $table.sousSystemeId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get niveauClasse => $composableBuilder(
    column: $table.niveauClasse,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get filiere => $composableBuilder(
    column: $table.filiere,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get capacite => $composableBuilder(
    column: $table.capacite,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get qrToken => $composableBuilder(
    column: $table.qrToken,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ClassesTableAnnotationComposer
    extends Composer<_$AppDatabase, $ClassesTable> {
  $$ClassesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get niveauId =>
      $composableBuilder(column: $table.niveauId, builder: (column) => column);

  GeneratedColumn<int> get niveauScolaireId => $composableBuilder(
    column: $table.niveauScolaireId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get anneeScolaireId => $composableBuilder(
    column: $table.anneeScolaireId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get professeurPrincipalId => $composableBuilder(
    column: $table.professeurPrincipalId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get titulaireId => $composableBuilder(
    column: $table.titulaireId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get surveillantGeneralId => $composableBuilder(
    column: $table.surveillantGeneralId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nom =>
      $composableBuilder(column: $table.nom, builder: (column) => column);

  GeneratedColumn<String> get sigle =>
      $composableBuilder(column: $table.sigle, builder: (column) => column);

  GeneratedColumn<int> get sousSystemeId => $composableBuilder(
    column: $table.sousSystemeId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get niveauClasse => $composableBuilder(
    column: $table.niveauClasse,
    builder: (column) => column,
  );

  GeneratedColumn<String> get filiere =>
      $composableBuilder(column: $table.filiere, builder: (column) => column);

  GeneratedColumn<int> get capacite =>
      $composableBuilder(column: $table.capacite, builder: (column) => column);

  GeneratedColumn<String> get qrToken =>
      $composableBuilder(column: $table.qrToken, builder: (column) => column);
}

class $$ClassesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $ClassesTable,
          ClassesData,
          $$ClassesTableFilterComposer,
          $$ClassesTableOrderingComposer,
          $$ClassesTableAnnotationComposer,
          $$ClassesTableCreateCompanionBuilder,
          $$ClassesTableUpdateCompanionBuilder,
          (
            ClassesData,
            BaseReferences<_$AppDatabase, $ClassesTable, ClassesData>,
          ),
          ClassesData,
          PrefetchHooks Function()
        > {
  $$ClassesTableTableManager(_$AppDatabase db, $ClassesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ClassesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ClassesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ClassesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<int?> niveauId = const Value.absent(),
                Value<int?> niveauScolaireId = const Value.absent(),
                Value<int?> anneeScolaireId = const Value.absent(),
                Value<int?> professeurPrincipalId = const Value.absent(),
                Value<int?> titulaireId = const Value.absent(),
                Value<int?> surveillantGeneralId = const Value.absent(),
                Value<String> nom = const Value.absent(),
                Value<String?> sigle = const Value.absent(),
                Value<int?> sousSystemeId = const Value.absent(),
                Value<String?> niveauClasse = const Value.absent(),
                Value<String?> filiere = const Value.absent(),
                Value<int?> capacite = const Value.absent(),
                Value<String?> qrToken = const Value.absent(),
              }) => ClassesCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                niveauId: niveauId,
                niveauScolaireId: niveauScolaireId,
                anneeScolaireId: anneeScolaireId,
                professeurPrincipalId: professeurPrincipalId,
                titulaireId: titulaireId,
                surveillantGeneralId: surveillantGeneralId,
                nom: nom,
                sigle: sigle,
                sousSystemeId: sousSystemeId,
                niveauClasse: niveauClasse,
                filiere: filiere,
                capacite: capacite,
                qrToken: qrToken,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                Value<int?> niveauId = const Value.absent(),
                Value<int?> niveauScolaireId = const Value.absent(),
                Value<int?> anneeScolaireId = const Value.absent(),
                Value<int?> professeurPrincipalId = const Value.absent(),
                Value<int?> titulaireId = const Value.absent(),
                Value<int?> surveillantGeneralId = const Value.absent(),
                required String nom,
                Value<String?> sigle = const Value.absent(),
                Value<int?> sousSystemeId = const Value.absent(),
                Value<String?> niveauClasse = const Value.absent(),
                Value<String?> filiere = const Value.absent(),
                Value<int?> capacite = const Value.absent(),
                Value<String?> qrToken = const Value.absent(),
              }) => ClassesCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                niveauId: niveauId,
                niveauScolaireId: niveauScolaireId,
                anneeScolaireId: anneeScolaireId,
                professeurPrincipalId: professeurPrincipalId,
                titulaireId: titulaireId,
                surveillantGeneralId: surveillantGeneralId,
                nom: nom,
                sigle: sigle,
                sousSystemeId: sousSystemeId,
                niveauClasse: niveauClasse,
                filiere: filiere,
                capacite: capacite,
                qrToken: qrToken,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ClassesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $ClassesTable,
      ClassesData,
      $$ClassesTableFilterComposer,
      $$ClassesTableOrderingComposer,
      $$ClassesTableAnnotationComposer,
      $$ClassesTableCreateCompanionBuilder,
      $$ClassesTableUpdateCompanionBuilder,
      (ClassesData, BaseReferences<_$AppDatabase, $ClassesTable, ClassesData>),
      ClassesData,
      PrefetchHooks Function()
    >;
typedef $$ClasseMatieresTableCreateCompanionBuilder =
    ClasseMatieresCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int classeId,
      required int matiereId,
      Value<int?> personnelId,
      Value<double> coefficient,
      Value<int?> quotaHoraire,
      Value<int> groupe,
      Value<String?> competences,
      Value<String?> statut,
    });
typedef $$ClasseMatieresTableUpdateCompanionBuilder =
    ClasseMatieresCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> classeId,
      Value<int> matiereId,
      Value<int?> personnelId,
      Value<double> coefficient,
      Value<int?> quotaHoraire,
      Value<int> groupe,
      Value<String?> competences,
      Value<String?> statut,
    });

class $$ClasseMatieresTableFilterComposer
    extends Composer<_$AppDatabase, $ClasseMatieresTable> {
  $$ClasseMatieresTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get matiereId => $composableBuilder(
    column: $table.matiereId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get personnelId => $composableBuilder(
    column: $table.personnelId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<double> get coefficient => $composableBuilder(
    column: $table.coefficient,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get quotaHoraire => $composableBuilder(
    column: $table.quotaHoraire,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get groupe => $composableBuilder(
    column: $table.groupe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get competences => $composableBuilder(
    column: $table.competences,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ClasseMatieresTableOrderingComposer
    extends Composer<_$AppDatabase, $ClasseMatieresTable> {
  $$ClasseMatieresTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get matiereId => $composableBuilder(
    column: $table.matiereId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get personnelId => $composableBuilder(
    column: $table.personnelId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<double> get coefficient => $composableBuilder(
    column: $table.coefficient,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get quotaHoraire => $composableBuilder(
    column: $table.quotaHoraire,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get groupe => $composableBuilder(
    column: $table.groupe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get competences => $composableBuilder(
    column: $table.competences,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ClasseMatieresTableAnnotationComposer
    extends Composer<_$AppDatabase, $ClasseMatieresTable> {
  $$ClasseMatieresTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get classeId =>
      $composableBuilder(column: $table.classeId, builder: (column) => column);

  GeneratedColumn<int> get matiereId =>
      $composableBuilder(column: $table.matiereId, builder: (column) => column);

  GeneratedColumn<int> get personnelId => $composableBuilder(
    column: $table.personnelId,
    builder: (column) => column,
  );

  GeneratedColumn<double> get coefficient => $composableBuilder(
    column: $table.coefficient,
    builder: (column) => column,
  );

  GeneratedColumn<int> get quotaHoraire => $composableBuilder(
    column: $table.quotaHoraire,
    builder: (column) => column,
  );

  GeneratedColumn<int> get groupe =>
      $composableBuilder(column: $table.groupe, builder: (column) => column);

  GeneratedColumn<String> get competences => $composableBuilder(
    column: $table.competences,
    builder: (column) => column,
  );

  GeneratedColumn<String> get statut =>
      $composableBuilder(column: $table.statut, builder: (column) => column);
}

class $$ClasseMatieresTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $ClasseMatieresTable,
          ClasseMatiere,
          $$ClasseMatieresTableFilterComposer,
          $$ClasseMatieresTableOrderingComposer,
          $$ClasseMatieresTableAnnotationComposer,
          $$ClasseMatieresTableCreateCompanionBuilder,
          $$ClasseMatieresTableUpdateCompanionBuilder,
          (
            ClasseMatiere,
            BaseReferences<_$AppDatabase, $ClasseMatieresTable, ClasseMatiere>,
          ),
          ClasseMatiere,
          PrefetchHooks Function()
        > {
  $$ClasseMatieresTableTableManager(
    _$AppDatabase db,
    $ClasseMatieresTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ClasseMatieresTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ClasseMatieresTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ClasseMatieresTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> classeId = const Value.absent(),
                Value<int> matiereId = const Value.absent(),
                Value<int?> personnelId = const Value.absent(),
                Value<double> coefficient = const Value.absent(),
                Value<int?> quotaHoraire = const Value.absent(),
                Value<int> groupe = const Value.absent(),
                Value<String?> competences = const Value.absent(),
                Value<String?> statut = const Value.absent(),
              }) => ClasseMatieresCompanion(
                id: id,
                etatSync: etatSync,
                classeId: classeId,
                matiereId: matiereId,
                personnelId: personnelId,
                coefficient: coefficient,
                quotaHoraire: quotaHoraire,
                groupe: groupe,
                competences: competences,
                statut: statut,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int classeId,
                required int matiereId,
                Value<int?> personnelId = const Value.absent(),
                Value<double> coefficient = const Value.absent(),
                Value<int?> quotaHoraire = const Value.absent(),
                Value<int> groupe = const Value.absent(),
                Value<String?> competences = const Value.absent(),
                Value<String?> statut = const Value.absent(),
              }) => ClasseMatieresCompanion.insert(
                id: id,
                etatSync: etatSync,
                classeId: classeId,
                matiereId: matiereId,
                personnelId: personnelId,
                coefficient: coefficient,
                quotaHoraire: quotaHoraire,
                groupe: groupe,
                competences: competences,
                statut: statut,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ClasseMatieresTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $ClasseMatieresTable,
      ClasseMatiere,
      $$ClasseMatieresTableFilterComposer,
      $$ClasseMatieresTableOrderingComposer,
      $$ClasseMatieresTableAnnotationComposer,
      $$ClasseMatieresTableCreateCompanionBuilder,
      $$ClasseMatieresTableUpdateCompanionBuilder,
      (
        ClasseMatiere,
        BaseReferences<_$AppDatabase, $ClasseMatieresTable, ClasseMatiere>,
      ),
      ClasseMatiere,
      PrefetchHooks Function()
    >;
typedef $$EmploisDuTempsTableCreateCompanionBuilder =
    EmploisDuTempsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      required int classeId,
      Value<int?> classeMatiereId,
      Value<String?> jour,
      Value<String?> heureDebut,
      Value<String?> heureFin,
      Value<String?> salle,
    });
typedef $$EmploisDuTempsTableUpdateCompanionBuilder =
    EmploisDuTempsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<int> classeId,
      Value<int?> classeMatiereId,
      Value<String?> jour,
      Value<String?> heureDebut,
      Value<String?> heureFin,
      Value<String?> salle,
    });

class $$EmploisDuTempsTableFilterComposer
    extends Composer<_$AppDatabase, $EmploisDuTempsTable> {
  $$EmploisDuTempsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get jour => $composableBuilder(
    column: $table.jour,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get heureDebut => $composableBuilder(
    column: $table.heureDebut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get heureFin => $composableBuilder(
    column: $table.heureFin,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get salle => $composableBuilder(
    column: $table.salle,
    builder: (column) => ColumnFilters(column),
  );
}

class $$EmploisDuTempsTableOrderingComposer
    extends Composer<_$AppDatabase, $EmploisDuTempsTable> {
  $$EmploisDuTempsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get jour => $composableBuilder(
    column: $table.jour,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get heureDebut => $composableBuilder(
    column: $table.heureDebut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get heureFin => $composableBuilder(
    column: $table.heureFin,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get salle => $composableBuilder(
    column: $table.salle,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$EmploisDuTempsTableAnnotationComposer
    extends Composer<_$AppDatabase, $EmploisDuTempsTable> {
  $$EmploisDuTempsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get classeId =>
      $composableBuilder(column: $table.classeId, builder: (column) => column);

  GeneratedColumn<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get jour =>
      $composableBuilder(column: $table.jour, builder: (column) => column);

  GeneratedColumn<String> get heureDebut => $composableBuilder(
    column: $table.heureDebut,
    builder: (column) => column,
  );

  GeneratedColumn<String> get heureFin =>
      $composableBuilder(column: $table.heureFin, builder: (column) => column);

  GeneratedColumn<String> get salle =>
      $composableBuilder(column: $table.salle, builder: (column) => column);
}

class $$EmploisDuTempsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $EmploisDuTempsTable,
          EmploisDuTemp,
          $$EmploisDuTempsTableFilterComposer,
          $$EmploisDuTempsTableOrderingComposer,
          $$EmploisDuTempsTableAnnotationComposer,
          $$EmploisDuTempsTableCreateCompanionBuilder,
          $$EmploisDuTempsTableUpdateCompanionBuilder,
          (
            EmploisDuTemp,
            BaseReferences<_$AppDatabase, $EmploisDuTempsTable, EmploisDuTemp>,
          ),
          EmploisDuTemp,
          PrefetchHooks Function()
        > {
  $$EmploisDuTempsTableTableManager(
    _$AppDatabase db,
    $EmploisDuTempsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$EmploisDuTempsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$EmploisDuTempsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$EmploisDuTempsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<int> classeId = const Value.absent(),
                Value<int?> classeMatiereId = const Value.absent(),
                Value<String?> jour = const Value.absent(),
                Value<String?> heureDebut = const Value.absent(),
                Value<String?> heureFin = const Value.absent(),
                Value<String?> salle = const Value.absent(),
              }) => EmploisDuTempsCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                classeId: classeId,
                classeMatiereId: classeMatiereId,
                jour: jour,
                heureDebut: heureDebut,
                heureFin: heureFin,
                salle: salle,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                required int classeId,
                Value<int?> classeMatiereId = const Value.absent(),
                Value<String?> jour = const Value.absent(),
                Value<String?> heureDebut = const Value.absent(),
                Value<String?> heureFin = const Value.absent(),
                Value<String?> salle = const Value.absent(),
              }) => EmploisDuTempsCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                classeId: classeId,
                classeMatiereId: classeMatiereId,
                jour: jour,
                heureDebut: heureDebut,
                heureFin: heureFin,
                salle: salle,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$EmploisDuTempsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $EmploisDuTempsTable,
      EmploisDuTemp,
      $$EmploisDuTempsTableFilterComposer,
      $$EmploisDuTempsTableOrderingComposer,
      $$EmploisDuTempsTableAnnotationComposer,
      $$EmploisDuTempsTableCreateCompanionBuilder,
      $$EmploisDuTempsTableUpdateCompanionBuilder,
      (
        EmploisDuTemp,
        BaseReferences<_$AppDatabase, $EmploisDuTempsTable, EmploisDuTemp>,
      ),
      EmploisDuTemp,
      PrefetchHooks Function()
    >;
typedef $$ProgressionItemsTableCreateCompanionBuilder =
    ProgressionItemsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int classeMatiereId,
      Value<int?> parentId,
      Value<String?> type,
      required String titre,
      Value<String?> description,
      Value<String?> objectifs,
      Value<String?> materiel,
      Value<String?> activites,
      Value<String?> devoirs,
      Value<int> ordre,
      Value<int?> sequenceId,
      Value<int?> dureePrevue,
    });
typedef $$ProgressionItemsTableUpdateCompanionBuilder =
    ProgressionItemsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> classeMatiereId,
      Value<int?> parentId,
      Value<String?> type,
      Value<String> titre,
      Value<String?> description,
      Value<String?> objectifs,
      Value<String?> materiel,
      Value<String?> activites,
      Value<String?> devoirs,
      Value<int> ordre,
      Value<int?> sequenceId,
      Value<int?> dureePrevue,
    });

class $$ProgressionItemsTableFilterComposer
    extends Composer<_$AppDatabase, $ProgressionItemsTable> {
  $$ProgressionItemsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get parentId => $composableBuilder(
    column: $table.parentId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get titre => $composableBuilder(
    column: $table.titre,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get description => $composableBuilder(
    column: $table.description,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get objectifs => $composableBuilder(
    column: $table.objectifs,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get materiel => $composableBuilder(
    column: $table.materiel,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get activites => $composableBuilder(
    column: $table.activites,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get devoirs => $composableBuilder(
    column: $table.devoirs,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get sequenceId => $composableBuilder(
    column: $table.sequenceId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get dureePrevue => $composableBuilder(
    column: $table.dureePrevue,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ProgressionItemsTableOrderingComposer
    extends Composer<_$AppDatabase, $ProgressionItemsTable> {
  $$ProgressionItemsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get parentId => $composableBuilder(
    column: $table.parentId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get titre => $composableBuilder(
    column: $table.titre,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get description => $composableBuilder(
    column: $table.description,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get objectifs => $composableBuilder(
    column: $table.objectifs,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get materiel => $composableBuilder(
    column: $table.materiel,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get activites => $composableBuilder(
    column: $table.activites,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get devoirs => $composableBuilder(
    column: $table.devoirs,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ordre => $composableBuilder(
    column: $table.ordre,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get sequenceId => $composableBuilder(
    column: $table.sequenceId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get dureePrevue => $composableBuilder(
    column: $table.dureePrevue,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ProgressionItemsTableAnnotationComposer
    extends Composer<_$AppDatabase, $ProgressionItemsTable> {
  $$ProgressionItemsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get parentId =>
      $composableBuilder(column: $table.parentId, builder: (column) => column);

  GeneratedColumn<String> get type =>
      $composableBuilder(column: $table.type, builder: (column) => column);

  GeneratedColumn<String> get titre =>
      $composableBuilder(column: $table.titre, builder: (column) => column);

  GeneratedColumn<String> get description => $composableBuilder(
    column: $table.description,
    builder: (column) => column,
  );

  GeneratedColumn<String> get objectifs =>
      $composableBuilder(column: $table.objectifs, builder: (column) => column);

  GeneratedColumn<String> get materiel =>
      $composableBuilder(column: $table.materiel, builder: (column) => column);

  GeneratedColumn<String> get activites =>
      $composableBuilder(column: $table.activites, builder: (column) => column);

  GeneratedColumn<String> get devoirs =>
      $composableBuilder(column: $table.devoirs, builder: (column) => column);

  GeneratedColumn<int> get ordre =>
      $composableBuilder(column: $table.ordre, builder: (column) => column);

  GeneratedColumn<int> get sequenceId => $composableBuilder(
    column: $table.sequenceId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get dureePrevue => $composableBuilder(
    column: $table.dureePrevue,
    builder: (column) => column,
  );
}

class $$ProgressionItemsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $ProgressionItemsTable,
          ProgressionItem,
          $$ProgressionItemsTableFilterComposer,
          $$ProgressionItemsTableOrderingComposer,
          $$ProgressionItemsTableAnnotationComposer,
          $$ProgressionItemsTableCreateCompanionBuilder,
          $$ProgressionItemsTableUpdateCompanionBuilder,
          (
            ProgressionItem,
            BaseReferences<
              _$AppDatabase,
              $ProgressionItemsTable,
              ProgressionItem
            >,
          ),
          ProgressionItem,
          PrefetchHooks Function()
        > {
  $$ProgressionItemsTableTableManager(
    _$AppDatabase db,
    $ProgressionItemsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ProgressionItemsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ProgressionItemsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ProgressionItemsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> classeMatiereId = const Value.absent(),
                Value<int?> parentId = const Value.absent(),
                Value<String?> type = const Value.absent(),
                Value<String> titre = const Value.absent(),
                Value<String?> description = const Value.absent(),
                Value<String?> objectifs = const Value.absent(),
                Value<String?> materiel = const Value.absent(),
                Value<String?> activites = const Value.absent(),
                Value<String?> devoirs = const Value.absent(),
                Value<int> ordre = const Value.absent(),
                Value<int?> sequenceId = const Value.absent(),
                Value<int?> dureePrevue = const Value.absent(),
              }) => ProgressionItemsCompanion(
                id: id,
                etatSync: etatSync,
                classeMatiereId: classeMatiereId,
                parentId: parentId,
                type: type,
                titre: titre,
                description: description,
                objectifs: objectifs,
                materiel: materiel,
                activites: activites,
                devoirs: devoirs,
                ordre: ordre,
                sequenceId: sequenceId,
                dureePrevue: dureePrevue,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int classeMatiereId,
                Value<int?> parentId = const Value.absent(),
                Value<String?> type = const Value.absent(),
                required String titre,
                Value<String?> description = const Value.absent(),
                Value<String?> objectifs = const Value.absent(),
                Value<String?> materiel = const Value.absent(),
                Value<String?> activites = const Value.absent(),
                Value<String?> devoirs = const Value.absent(),
                Value<int> ordre = const Value.absent(),
                Value<int?> sequenceId = const Value.absent(),
                Value<int?> dureePrevue = const Value.absent(),
              }) => ProgressionItemsCompanion.insert(
                id: id,
                etatSync: etatSync,
                classeMatiereId: classeMatiereId,
                parentId: parentId,
                type: type,
                titre: titre,
                description: description,
                objectifs: objectifs,
                materiel: materiel,
                activites: activites,
                devoirs: devoirs,
                ordre: ordre,
                sequenceId: sequenceId,
                dureePrevue: dureePrevue,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ProgressionItemsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $ProgressionItemsTable,
      ProgressionItem,
      $$ProgressionItemsTableFilterComposer,
      $$ProgressionItemsTableOrderingComposer,
      $$ProgressionItemsTableAnnotationComposer,
      $$ProgressionItemsTableCreateCompanionBuilder,
      $$ProgressionItemsTableUpdateCompanionBuilder,
      (
        ProgressionItem,
        BaseReferences<_$AppDatabase, $ProgressionItemsTable, ProgressionItem>,
      ),
      ProgressionItem,
      PrefetchHooks Function()
    >;
typedef $$ElevesTableCreateCompanionBuilder =
    ElevesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      Value<int?> classeId,
      Value<String?> matricule,
      required String nomComplet,
      Value<String?> sexe,
      Value<String?> dateNaissance,
      Value<String?> lieuNaissance,
      Value<String?> nationalite,
      Value<bool> redoublant,
      Value<String?> statut,
      Value<String?> photoPath,
    });
typedef $$ElevesTableUpdateCompanionBuilder =
    ElevesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<int?> classeId,
      Value<String?> matricule,
      Value<String> nomComplet,
      Value<String?> sexe,
      Value<String?> dateNaissance,
      Value<String?> lieuNaissance,
      Value<String?> nationalite,
      Value<bool> redoublant,
      Value<String?> statut,
      Value<String?> photoPath,
    });

class $$ElevesTableFilterComposer
    extends Composer<_$AppDatabase, $ElevesTable> {
  $$ElevesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get matricule => $composableBuilder(
    column: $table.matricule,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nomComplet => $composableBuilder(
    column: $table.nomComplet,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sexe => $composableBuilder(
    column: $table.sexe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateNaissance => $composableBuilder(
    column: $table.dateNaissance,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lieuNaissance => $composableBuilder(
    column: $table.lieuNaissance,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nationalite => $composableBuilder(
    column: $table.nationalite,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get redoublant => $composableBuilder(
    column: $table.redoublant,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get photoPath => $composableBuilder(
    column: $table.photoPath,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ElevesTableOrderingComposer
    extends Composer<_$AppDatabase, $ElevesTable> {
  $$ElevesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get matricule => $composableBuilder(
    column: $table.matricule,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nomComplet => $composableBuilder(
    column: $table.nomComplet,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sexe => $composableBuilder(
    column: $table.sexe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateNaissance => $composableBuilder(
    column: $table.dateNaissance,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lieuNaissance => $composableBuilder(
    column: $table.lieuNaissance,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nationalite => $composableBuilder(
    column: $table.nationalite,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get redoublant => $composableBuilder(
    column: $table.redoublant,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get photoPath => $composableBuilder(
    column: $table.photoPath,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ElevesTableAnnotationComposer
    extends Composer<_$AppDatabase, $ElevesTable> {
  $$ElevesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get classeId =>
      $composableBuilder(column: $table.classeId, builder: (column) => column);

  GeneratedColumn<String> get matricule =>
      $composableBuilder(column: $table.matricule, builder: (column) => column);

  GeneratedColumn<String> get nomComplet => $composableBuilder(
    column: $table.nomComplet,
    builder: (column) => column,
  );

  GeneratedColumn<String> get sexe =>
      $composableBuilder(column: $table.sexe, builder: (column) => column);

  GeneratedColumn<String> get dateNaissance => $composableBuilder(
    column: $table.dateNaissance,
    builder: (column) => column,
  );

  GeneratedColumn<String> get lieuNaissance => $composableBuilder(
    column: $table.lieuNaissance,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nationalite => $composableBuilder(
    column: $table.nationalite,
    builder: (column) => column,
  );

  GeneratedColumn<bool> get redoublant => $composableBuilder(
    column: $table.redoublant,
    builder: (column) => column,
  );

  GeneratedColumn<String> get statut =>
      $composableBuilder(column: $table.statut, builder: (column) => column);

  GeneratedColumn<String> get photoPath =>
      $composableBuilder(column: $table.photoPath, builder: (column) => column);
}

class $$ElevesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $ElevesTable,
          Eleve,
          $$ElevesTableFilterComposer,
          $$ElevesTableOrderingComposer,
          $$ElevesTableAnnotationComposer,
          $$ElevesTableCreateCompanionBuilder,
          $$ElevesTableUpdateCompanionBuilder,
          (Eleve, BaseReferences<_$AppDatabase, $ElevesTable, Eleve>),
          Eleve,
          PrefetchHooks Function()
        > {
  $$ElevesTableTableManager(_$AppDatabase db, $ElevesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ElevesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ElevesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ElevesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<int?> classeId = const Value.absent(),
                Value<String?> matricule = const Value.absent(),
                Value<String> nomComplet = const Value.absent(),
                Value<String?> sexe = const Value.absent(),
                Value<String?> dateNaissance = const Value.absent(),
                Value<String?> lieuNaissance = const Value.absent(),
                Value<String?> nationalite = const Value.absent(),
                Value<bool> redoublant = const Value.absent(),
                Value<String?> statut = const Value.absent(),
                Value<String?> photoPath = const Value.absent(),
              }) => ElevesCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                classeId: classeId,
                matricule: matricule,
                nomComplet: nomComplet,
                sexe: sexe,
                dateNaissance: dateNaissance,
                lieuNaissance: lieuNaissance,
                nationalite: nationalite,
                redoublant: redoublant,
                statut: statut,
                photoPath: photoPath,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                Value<int?> classeId = const Value.absent(),
                Value<String?> matricule = const Value.absent(),
                required String nomComplet,
                Value<String?> sexe = const Value.absent(),
                Value<String?> dateNaissance = const Value.absent(),
                Value<String?> lieuNaissance = const Value.absent(),
                Value<String?> nationalite = const Value.absent(),
                Value<bool> redoublant = const Value.absent(),
                Value<String?> statut = const Value.absent(),
                Value<String?> photoPath = const Value.absent(),
              }) => ElevesCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                classeId: classeId,
                matricule: matricule,
                nomComplet: nomComplet,
                sexe: sexe,
                dateNaissance: dateNaissance,
                lieuNaissance: lieuNaissance,
                nationalite: nationalite,
                redoublant: redoublant,
                statut: statut,
                photoPath: photoPath,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ElevesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $ElevesTable,
      Eleve,
      $$ElevesTableFilterComposer,
      $$ElevesTableOrderingComposer,
      $$ElevesTableAnnotationComposer,
      $$ElevesTableCreateCompanionBuilder,
      $$ElevesTableUpdateCompanionBuilder,
      (Eleve, BaseReferences<_$AppDatabase, $ElevesTable, Eleve>),
      Eleve,
      PrefetchHooks Function()
    >;
typedef $$PersonnelsTableCreateCompanionBuilder =
    PersonnelsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      Value<int?> departementId,
      Value<int?> fonctionId,
      Value<String?> matricule,
      required String nomComplet,
      Value<String?> civilite,
      Value<String?> sexe,
      Value<String?> telephone,
      Value<String?> email,
      Value<String?> statut,
      Value<String?> photoPath,
    });
typedef $$PersonnelsTableUpdateCompanionBuilder =
    PersonnelsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<int?> departementId,
      Value<int?> fonctionId,
      Value<String?> matricule,
      Value<String> nomComplet,
      Value<String?> civilite,
      Value<String?> sexe,
      Value<String?> telephone,
      Value<String?> email,
      Value<String?> statut,
      Value<String?> photoPath,
    });

class $$PersonnelsTableFilterComposer
    extends Composer<_$AppDatabase, $PersonnelsTable> {
  $$PersonnelsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get departementId => $composableBuilder(
    column: $table.departementId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get fonctionId => $composableBuilder(
    column: $table.fonctionId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get matricule => $composableBuilder(
    column: $table.matricule,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nomComplet => $composableBuilder(
    column: $table.nomComplet,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get civilite => $composableBuilder(
    column: $table.civilite,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sexe => $composableBuilder(
    column: $table.sexe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get telephone => $composableBuilder(
    column: $table.telephone,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get email => $composableBuilder(
    column: $table.email,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get photoPath => $composableBuilder(
    column: $table.photoPath,
    builder: (column) => ColumnFilters(column),
  );
}

class $$PersonnelsTableOrderingComposer
    extends Composer<_$AppDatabase, $PersonnelsTable> {
  $$PersonnelsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get departementId => $composableBuilder(
    column: $table.departementId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get fonctionId => $composableBuilder(
    column: $table.fonctionId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get matricule => $composableBuilder(
    column: $table.matricule,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nomComplet => $composableBuilder(
    column: $table.nomComplet,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get civilite => $composableBuilder(
    column: $table.civilite,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sexe => $composableBuilder(
    column: $table.sexe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get telephone => $composableBuilder(
    column: $table.telephone,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get email => $composableBuilder(
    column: $table.email,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get photoPath => $composableBuilder(
    column: $table.photoPath,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PersonnelsTableAnnotationComposer
    extends Composer<_$AppDatabase, $PersonnelsTable> {
  $$PersonnelsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get departementId => $composableBuilder(
    column: $table.departementId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get fonctionId => $composableBuilder(
    column: $table.fonctionId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get matricule =>
      $composableBuilder(column: $table.matricule, builder: (column) => column);

  GeneratedColumn<String> get nomComplet => $composableBuilder(
    column: $table.nomComplet,
    builder: (column) => column,
  );

  GeneratedColumn<String> get civilite =>
      $composableBuilder(column: $table.civilite, builder: (column) => column);

  GeneratedColumn<String> get sexe =>
      $composableBuilder(column: $table.sexe, builder: (column) => column);

  GeneratedColumn<String> get telephone =>
      $composableBuilder(column: $table.telephone, builder: (column) => column);

  GeneratedColumn<String> get email =>
      $composableBuilder(column: $table.email, builder: (column) => column);

  GeneratedColumn<String> get statut =>
      $composableBuilder(column: $table.statut, builder: (column) => column);

  GeneratedColumn<String> get photoPath =>
      $composableBuilder(column: $table.photoPath, builder: (column) => column);
}

class $$PersonnelsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $PersonnelsTable,
          Personnel,
          $$PersonnelsTableFilterComposer,
          $$PersonnelsTableOrderingComposer,
          $$PersonnelsTableAnnotationComposer,
          $$PersonnelsTableCreateCompanionBuilder,
          $$PersonnelsTableUpdateCompanionBuilder,
          (
            Personnel,
            BaseReferences<_$AppDatabase, $PersonnelsTable, Personnel>,
          ),
          Personnel,
          PrefetchHooks Function()
        > {
  $$PersonnelsTableTableManager(_$AppDatabase db, $PersonnelsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PersonnelsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PersonnelsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PersonnelsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<int?> departementId = const Value.absent(),
                Value<int?> fonctionId = const Value.absent(),
                Value<String?> matricule = const Value.absent(),
                Value<String> nomComplet = const Value.absent(),
                Value<String?> civilite = const Value.absent(),
                Value<String?> sexe = const Value.absent(),
                Value<String?> telephone = const Value.absent(),
                Value<String?> email = const Value.absent(),
                Value<String?> statut = const Value.absent(),
                Value<String?> photoPath = const Value.absent(),
              }) => PersonnelsCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                departementId: departementId,
                fonctionId: fonctionId,
                matricule: matricule,
                nomComplet: nomComplet,
                civilite: civilite,
                sexe: sexe,
                telephone: telephone,
                email: email,
                statut: statut,
                photoPath: photoPath,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                Value<int?> departementId = const Value.absent(),
                Value<int?> fonctionId = const Value.absent(),
                Value<String?> matricule = const Value.absent(),
                required String nomComplet,
                Value<String?> civilite = const Value.absent(),
                Value<String?> sexe = const Value.absent(),
                Value<String?> telephone = const Value.absent(),
                Value<String?> email = const Value.absent(),
                Value<String?> statut = const Value.absent(),
                Value<String?> photoPath = const Value.absent(),
              }) => PersonnelsCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                departementId: departementId,
                fonctionId: fonctionId,
                matricule: matricule,
                nomComplet: nomComplet,
                civilite: civilite,
                sexe: sexe,
                telephone: telephone,
                email: email,
                statut: statut,
                photoPath: photoPath,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PersonnelsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $PersonnelsTable,
      Personnel,
      $$PersonnelsTableFilterComposer,
      $$PersonnelsTableOrderingComposer,
      $$PersonnelsTableAnnotationComposer,
      $$PersonnelsTableCreateCompanionBuilder,
      $$PersonnelsTableUpdateCompanionBuilder,
      (Personnel, BaseReferences<_$AppDatabase, $PersonnelsTable, Personnel>),
      Personnel,
      PrefetchHooks Function()
    >;
typedef $$SeancesTableCreateCompanionBuilder =
    SeancesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      required int classeId,
      Value<int?> classeMatiereId,
      Value<int?> trimestreId,
      Value<int?> emploiDuTempsId,
      Value<String?> dateSeance,
      Value<String?> heureDebut,
      Value<String?> heureFin,
      Value<String?> salle,
      Value<String?> contenu,
      Value<String?> observations,
      Value<String?> donneesPersonnalisees,
      Value<String?> statut,
    });
typedef $$SeancesTableUpdateCompanionBuilder =
    SeancesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<int> classeId,
      Value<int?> classeMatiereId,
      Value<int?> trimestreId,
      Value<int?> emploiDuTempsId,
      Value<String?> dateSeance,
      Value<String?> heureDebut,
      Value<String?> heureFin,
      Value<String?> salle,
      Value<String?> contenu,
      Value<String?> observations,
      Value<String?> donneesPersonnalisees,
      Value<String?> statut,
    });

class $$SeancesTableFilterComposer
    extends Composer<_$AppDatabase, $SeancesTable> {
  $$SeancesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get emploiDuTempsId => $composableBuilder(
    column: $table.emploiDuTempsId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateSeance => $composableBuilder(
    column: $table.dateSeance,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get heureDebut => $composableBuilder(
    column: $table.heureDebut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get heureFin => $composableBuilder(
    column: $table.heureFin,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get salle => $composableBuilder(
    column: $table.salle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get contenu => $composableBuilder(
    column: $table.contenu,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get observations => $composableBuilder(
    column: $table.observations,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get donneesPersonnalisees => $composableBuilder(
    column: $table.donneesPersonnalisees,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnFilters(column),
  );
}

class $$SeancesTableOrderingComposer
    extends Composer<_$AppDatabase, $SeancesTable> {
  $$SeancesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get emploiDuTempsId => $composableBuilder(
    column: $table.emploiDuTempsId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateSeance => $composableBuilder(
    column: $table.dateSeance,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get heureDebut => $composableBuilder(
    column: $table.heureDebut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get heureFin => $composableBuilder(
    column: $table.heureFin,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get salle => $composableBuilder(
    column: $table.salle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get contenu => $composableBuilder(
    column: $table.contenu,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get observations => $composableBuilder(
    column: $table.observations,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get donneesPersonnalisees => $composableBuilder(
    column: $table.donneesPersonnalisees,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$SeancesTableAnnotationComposer
    extends Composer<_$AppDatabase, $SeancesTable> {
  $$SeancesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get classeId =>
      $composableBuilder(column: $table.classeId, builder: (column) => column);

  GeneratedColumn<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get emploiDuTempsId => $composableBuilder(
    column: $table.emploiDuTempsId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get dateSeance => $composableBuilder(
    column: $table.dateSeance,
    builder: (column) => column,
  );

  GeneratedColumn<String> get heureDebut => $composableBuilder(
    column: $table.heureDebut,
    builder: (column) => column,
  );

  GeneratedColumn<String> get heureFin =>
      $composableBuilder(column: $table.heureFin, builder: (column) => column);

  GeneratedColumn<String> get salle =>
      $composableBuilder(column: $table.salle, builder: (column) => column);

  GeneratedColumn<String> get contenu =>
      $composableBuilder(column: $table.contenu, builder: (column) => column);

  GeneratedColumn<String> get observations => $composableBuilder(
    column: $table.observations,
    builder: (column) => column,
  );

  GeneratedColumn<String> get donneesPersonnalisees => $composableBuilder(
    column: $table.donneesPersonnalisees,
    builder: (column) => column,
  );

  GeneratedColumn<String> get statut =>
      $composableBuilder(column: $table.statut, builder: (column) => column);
}

class $$SeancesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $SeancesTable,
          Seance,
          $$SeancesTableFilterComposer,
          $$SeancesTableOrderingComposer,
          $$SeancesTableAnnotationComposer,
          $$SeancesTableCreateCompanionBuilder,
          $$SeancesTableUpdateCompanionBuilder,
          (Seance, BaseReferences<_$AppDatabase, $SeancesTable, Seance>),
          Seance,
          PrefetchHooks Function()
        > {
  $$SeancesTableTableManager(_$AppDatabase db, $SeancesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SeancesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SeancesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SeancesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<int> classeId = const Value.absent(),
                Value<int?> classeMatiereId = const Value.absent(),
                Value<int?> trimestreId = const Value.absent(),
                Value<int?> emploiDuTempsId = const Value.absent(),
                Value<String?> dateSeance = const Value.absent(),
                Value<String?> heureDebut = const Value.absent(),
                Value<String?> heureFin = const Value.absent(),
                Value<String?> salle = const Value.absent(),
                Value<String?> contenu = const Value.absent(),
                Value<String?> observations = const Value.absent(),
                Value<String?> donneesPersonnalisees = const Value.absent(),
                Value<String?> statut = const Value.absent(),
              }) => SeancesCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                classeId: classeId,
                classeMatiereId: classeMatiereId,
                trimestreId: trimestreId,
                emploiDuTempsId: emploiDuTempsId,
                dateSeance: dateSeance,
                heureDebut: heureDebut,
                heureFin: heureFin,
                salle: salle,
                contenu: contenu,
                observations: observations,
                donneesPersonnalisees: donneesPersonnalisees,
                statut: statut,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                required int classeId,
                Value<int?> classeMatiereId = const Value.absent(),
                Value<int?> trimestreId = const Value.absent(),
                Value<int?> emploiDuTempsId = const Value.absent(),
                Value<String?> dateSeance = const Value.absent(),
                Value<String?> heureDebut = const Value.absent(),
                Value<String?> heureFin = const Value.absent(),
                Value<String?> salle = const Value.absent(),
                Value<String?> contenu = const Value.absent(),
                Value<String?> observations = const Value.absent(),
                Value<String?> donneesPersonnalisees = const Value.absent(),
                Value<String?> statut = const Value.absent(),
              }) => SeancesCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                classeId: classeId,
                classeMatiereId: classeMatiereId,
                trimestreId: trimestreId,
                emploiDuTempsId: emploiDuTempsId,
                dateSeance: dateSeance,
                heureDebut: heureDebut,
                heureFin: heureFin,
                salle: salle,
                contenu: contenu,
                observations: observations,
                donneesPersonnalisees: donneesPersonnalisees,
                statut: statut,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$SeancesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $SeancesTable,
      Seance,
      $$SeancesTableFilterComposer,
      $$SeancesTableOrderingComposer,
      $$SeancesTableAnnotationComposer,
      $$SeancesTableCreateCompanionBuilder,
      $$SeancesTableUpdateCompanionBuilder,
      (Seance, BaseReferences<_$AppDatabase, $SeancesTable, Seance>),
      Seance,
      PrefetchHooks Function()
    >;
typedef $$PresencesTableCreateCompanionBuilder =
    PresencesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int seanceId,
      required int eleveId,
      Value<String?> statut,
      Value<String?> motif,
      Value<bool> justifie,
      Value<String?> remarque,
    });
typedef $$PresencesTableUpdateCompanionBuilder =
    PresencesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> seanceId,
      Value<int> eleveId,
      Value<String?> statut,
      Value<String?> motif,
      Value<bool> justifie,
      Value<String?> remarque,
    });

class $$PresencesTableFilterComposer
    extends Composer<_$AppDatabase, $PresencesTable> {
  $$PresencesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get seanceId => $composableBuilder(
    column: $table.seanceId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get eleveId => $composableBuilder(
    column: $table.eleveId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get motif => $composableBuilder(
    column: $table.motif,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get justifie => $composableBuilder(
    column: $table.justifie,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get remarque => $composableBuilder(
    column: $table.remarque,
    builder: (column) => ColumnFilters(column),
  );
}

class $$PresencesTableOrderingComposer
    extends Composer<_$AppDatabase, $PresencesTable> {
  $$PresencesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get seanceId => $composableBuilder(
    column: $table.seanceId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get eleveId => $composableBuilder(
    column: $table.eleveId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get motif => $composableBuilder(
    column: $table.motif,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get justifie => $composableBuilder(
    column: $table.justifie,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get remarque => $composableBuilder(
    column: $table.remarque,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PresencesTableAnnotationComposer
    extends Composer<_$AppDatabase, $PresencesTable> {
  $$PresencesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get seanceId =>
      $composableBuilder(column: $table.seanceId, builder: (column) => column);

  GeneratedColumn<int> get eleveId =>
      $composableBuilder(column: $table.eleveId, builder: (column) => column);

  GeneratedColumn<String> get statut =>
      $composableBuilder(column: $table.statut, builder: (column) => column);

  GeneratedColumn<String> get motif =>
      $composableBuilder(column: $table.motif, builder: (column) => column);

  GeneratedColumn<bool> get justifie =>
      $composableBuilder(column: $table.justifie, builder: (column) => column);

  GeneratedColumn<String> get remarque =>
      $composableBuilder(column: $table.remarque, builder: (column) => column);
}

class $$PresencesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $PresencesTable,
          Presence,
          $$PresencesTableFilterComposer,
          $$PresencesTableOrderingComposer,
          $$PresencesTableAnnotationComposer,
          $$PresencesTableCreateCompanionBuilder,
          $$PresencesTableUpdateCompanionBuilder,
          (Presence, BaseReferences<_$AppDatabase, $PresencesTable, Presence>),
          Presence,
          PrefetchHooks Function()
        > {
  $$PresencesTableTableManager(_$AppDatabase db, $PresencesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PresencesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PresencesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PresencesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> seanceId = const Value.absent(),
                Value<int> eleveId = const Value.absent(),
                Value<String?> statut = const Value.absent(),
                Value<String?> motif = const Value.absent(),
                Value<bool> justifie = const Value.absent(),
                Value<String?> remarque = const Value.absent(),
              }) => PresencesCompanion(
                id: id,
                etatSync: etatSync,
                seanceId: seanceId,
                eleveId: eleveId,
                statut: statut,
                motif: motif,
                justifie: justifie,
                remarque: remarque,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int seanceId,
                required int eleveId,
                Value<String?> statut = const Value.absent(),
                Value<String?> motif = const Value.absent(),
                Value<bool> justifie = const Value.absent(),
                Value<String?> remarque = const Value.absent(),
              }) => PresencesCompanion.insert(
                id: id,
                etatSync: etatSync,
                seanceId: seanceId,
                eleveId: eleveId,
                statut: statut,
                motif: motif,
                justifie: justifie,
                remarque: remarque,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PresencesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $PresencesTable,
      Presence,
      $$PresencesTableFilterComposer,
      $$PresencesTableOrderingComposer,
      $$PresencesTableAnnotationComposer,
      $$PresencesTableCreateCompanionBuilder,
      $$PresencesTableUpdateCompanionBuilder,
      (Presence, BaseReferences<_$AppDatabase, $PresencesTable, Presence>),
      Presence,
      PrefetchHooks Function()
    >;
typedef $$NotesTableCreateCompanionBuilder =
    NotesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int eleveId,
      required int classeMatiereId,
      Value<int?> sequenceId,
      Value<String?> composante,
      Value<double?> valeur,
      Value<int?> saisiPar,
    });
typedef $$NotesTableUpdateCompanionBuilder =
    NotesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> eleveId,
      Value<int> classeMatiereId,
      Value<int?> sequenceId,
      Value<String?> composante,
      Value<double?> valeur,
      Value<int?> saisiPar,
    });

class $$NotesTableFilterComposer extends Composer<_$AppDatabase, $NotesTable> {
  $$NotesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get eleveId => $composableBuilder(
    column: $table.eleveId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get sequenceId => $composableBuilder(
    column: $table.sequenceId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get composante => $composableBuilder(
    column: $table.composante,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<double> get valeur => $composableBuilder(
    column: $table.valeur,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get saisiPar => $composableBuilder(
    column: $table.saisiPar,
    builder: (column) => ColumnFilters(column),
  );
}

class $$NotesTableOrderingComposer
    extends Composer<_$AppDatabase, $NotesTable> {
  $$NotesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get eleveId => $composableBuilder(
    column: $table.eleveId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get sequenceId => $composableBuilder(
    column: $table.sequenceId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get composante => $composableBuilder(
    column: $table.composante,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<double> get valeur => $composableBuilder(
    column: $table.valeur,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get saisiPar => $composableBuilder(
    column: $table.saisiPar,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$NotesTableAnnotationComposer
    extends Composer<_$AppDatabase, $NotesTable> {
  $$NotesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get eleveId =>
      $composableBuilder(column: $table.eleveId, builder: (column) => column);

  GeneratedColumn<int> get classeMatiereId => $composableBuilder(
    column: $table.classeMatiereId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get sequenceId => $composableBuilder(
    column: $table.sequenceId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get composante => $composableBuilder(
    column: $table.composante,
    builder: (column) => column,
  );

  GeneratedColumn<double> get valeur =>
      $composableBuilder(column: $table.valeur, builder: (column) => column);

  GeneratedColumn<int> get saisiPar =>
      $composableBuilder(column: $table.saisiPar, builder: (column) => column);
}

class $$NotesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $NotesTable,
          Note,
          $$NotesTableFilterComposer,
          $$NotesTableOrderingComposer,
          $$NotesTableAnnotationComposer,
          $$NotesTableCreateCompanionBuilder,
          $$NotesTableUpdateCompanionBuilder,
          (Note, BaseReferences<_$AppDatabase, $NotesTable, Note>),
          Note,
          PrefetchHooks Function()
        > {
  $$NotesTableTableManager(_$AppDatabase db, $NotesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$NotesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$NotesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$NotesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> eleveId = const Value.absent(),
                Value<int> classeMatiereId = const Value.absent(),
                Value<int?> sequenceId = const Value.absent(),
                Value<String?> composante = const Value.absent(),
                Value<double?> valeur = const Value.absent(),
                Value<int?> saisiPar = const Value.absent(),
              }) => NotesCompanion(
                id: id,
                etatSync: etatSync,
                eleveId: eleveId,
                classeMatiereId: classeMatiereId,
                sequenceId: sequenceId,
                composante: composante,
                valeur: valeur,
                saisiPar: saisiPar,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int eleveId,
                required int classeMatiereId,
                Value<int?> sequenceId = const Value.absent(),
                Value<String?> composante = const Value.absent(),
                Value<double?> valeur = const Value.absent(),
                Value<int?> saisiPar = const Value.absent(),
              }) => NotesCompanion.insert(
                id: id,
                etatSync: etatSync,
                eleveId: eleveId,
                classeMatiereId: classeMatiereId,
                sequenceId: sequenceId,
                composante: composante,
                valeur: valeur,
                saisiPar: saisiPar,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$NotesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $NotesTable,
      Note,
      $$NotesTableFilterComposer,
      $$NotesTableOrderingComposer,
      $$NotesTableAnnotationComposer,
      $$NotesTableCreateCompanionBuilder,
      $$NotesTableUpdateCompanionBuilder,
      (Note, BaseReferences<_$AppDatabase, $NotesTable, Note>),
      Note,
      PrefetchHooks Function()
    >;
typedef $$SanctionsTableCreateCompanionBuilder =
    SanctionsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int eleveId,
      Value<int?> classeId,
      Value<int?> trimestreId,
      required String type,
      Value<int?> dureeJours,
      Value<String?> dateDebut,
      Value<String?> dateFin,
      Value<String?> motif,
      Value<String?> commentaire,
      Value<String?> dateSanction,
      Value<String?> statut,
      Value<bool> impacteBulletin,
      Value<int?> enregistrePar,
    });
typedef $$SanctionsTableUpdateCompanionBuilder =
    SanctionsCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> eleveId,
      Value<int?> classeId,
      Value<int?> trimestreId,
      Value<String> type,
      Value<int?> dureeJours,
      Value<String?> dateDebut,
      Value<String?> dateFin,
      Value<String?> motif,
      Value<String?> commentaire,
      Value<String?> dateSanction,
      Value<String?> statut,
      Value<bool> impacteBulletin,
      Value<int?> enregistrePar,
    });

class $$SanctionsTableFilterComposer
    extends Composer<_$AppDatabase, $SanctionsTable> {
  $$SanctionsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get eleveId => $composableBuilder(
    column: $table.eleveId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get dureeJours => $composableBuilder(
    column: $table.dureeJours,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateDebut => $composableBuilder(
    column: $table.dateDebut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateFin => $composableBuilder(
    column: $table.dateFin,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get motif => $composableBuilder(
    column: $table.motif,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get commentaire => $composableBuilder(
    column: $table.commentaire,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dateSanction => $composableBuilder(
    column: $table.dateSanction,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get impacteBulletin => $composableBuilder(
    column: $table.impacteBulletin,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get enregistrePar => $composableBuilder(
    column: $table.enregistrePar,
    builder: (column) => ColumnFilters(column),
  );
}

class $$SanctionsTableOrderingComposer
    extends Composer<_$AppDatabase, $SanctionsTable> {
  $$SanctionsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get eleveId => $composableBuilder(
    column: $table.eleveId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get classeId => $composableBuilder(
    column: $table.classeId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get dureeJours => $composableBuilder(
    column: $table.dureeJours,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateDebut => $composableBuilder(
    column: $table.dateDebut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateFin => $composableBuilder(
    column: $table.dateFin,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get motif => $composableBuilder(
    column: $table.motif,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get commentaire => $composableBuilder(
    column: $table.commentaire,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dateSanction => $composableBuilder(
    column: $table.dateSanction,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get statut => $composableBuilder(
    column: $table.statut,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get impacteBulletin => $composableBuilder(
    column: $table.impacteBulletin,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get enregistrePar => $composableBuilder(
    column: $table.enregistrePar,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$SanctionsTableAnnotationComposer
    extends Composer<_$AppDatabase, $SanctionsTable> {
  $$SanctionsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get eleveId =>
      $composableBuilder(column: $table.eleveId, builder: (column) => column);

  GeneratedColumn<int> get classeId =>
      $composableBuilder(column: $table.classeId, builder: (column) => column);

  GeneratedColumn<int> get trimestreId => $composableBuilder(
    column: $table.trimestreId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get type =>
      $composableBuilder(column: $table.type, builder: (column) => column);

  GeneratedColumn<int> get dureeJours => $composableBuilder(
    column: $table.dureeJours,
    builder: (column) => column,
  );

  GeneratedColumn<String> get dateDebut =>
      $composableBuilder(column: $table.dateDebut, builder: (column) => column);

  GeneratedColumn<String> get dateFin =>
      $composableBuilder(column: $table.dateFin, builder: (column) => column);

  GeneratedColumn<String> get motif =>
      $composableBuilder(column: $table.motif, builder: (column) => column);

  GeneratedColumn<String> get commentaire => $composableBuilder(
    column: $table.commentaire,
    builder: (column) => column,
  );

  GeneratedColumn<String> get dateSanction => $composableBuilder(
    column: $table.dateSanction,
    builder: (column) => column,
  );

  GeneratedColumn<String> get statut =>
      $composableBuilder(column: $table.statut, builder: (column) => column);

  GeneratedColumn<bool> get impacteBulletin => $composableBuilder(
    column: $table.impacteBulletin,
    builder: (column) => column,
  );

  GeneratedColumn<int> get enregistrePar => $composableBuilder(
    column: $table.enregistrePar,
    builder: (column) => column,
  );
}

class $$SanctionsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $SanctionsTable,
          Sanction,
          $$SanctionsTableFilterComposer,
          $$SanctionsTableOrderingComposer,
          $$SanctionsTableAnnotationComposer,
          $$SanctionsTableCreateCompanionBuilder,
          $$SanctionsTableUpdateCompanionBuilder,
          (Sanction, BaseReferences<_$AppDatabase, $SanctionsTable, Sanction>),
          Sanction,
          PrefetchHooks Function()
        > {
  $$SanctionsTableTableManager(_$AppDatabase db, $SanctionsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SanctionsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SanctionsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SanctionsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> eleveId = const Value.absent(),
                Value<int?> classeId = const Value.absent(),
                Value<int?> trimestreId = const Value.absent(),
                Value<String> type = const Value.absent(),
                Value<int?> dureeJours = const Value.absent(),
                Value<String?> dateDebut = const Value.absent(),
                Value<String?> dateFin = const Value.absent(),
                Value<String?> motif = const Value.absent(),
                Value<String?> commentaire = const Value.absent(),
                Value<String?> dateSanction = const Value.absent(),
                Value<String?> statut = const Value.absent(),
                Value<bool> impacteBulletin = const Value.absent(),
                Value<int?> enregistrePar = const Value.absent(),
              }) => SanctionsCompanion(
                id: id,
                etatSync: etatSync,
                eleveId: eleveId,
                classeId: classeId,
                trimestreId: trimestreId,
                type: type,
                dureeJours: dureeJours,
                dateDebut: dateDebut,
                dateFin: dateFin,
                motif: motif,
                commentaire: commentaire,
                dateSanction: dateSanction,
                statut: statut,
                impacteBulletin: impacteBulletin,
                enregistrePar: enregistrePar,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int eleveId,
                Value<int?> classeId = const Value.absent(),
                Value<int?> trimestreId = const Value.absent(),
                required String type,
                Value<int?> dureeJours = const Value.absent(),
                Value<String?> dateDebut = const Value.absent(),
                Value<String?> dateFin = const Value.absent(),
                Value<String?> motif = const Value.absent(),
                Value<String?> commentaire = const Value.absent(),
                Value<String?> dateSanction = const Value.absent(),
                Value<String?> statut = const Value.absent(),
                Value<bool> impacteBulletin = const Value.absent(),
                Value<int?> enregistrePar = const Value.absent(),
              }) => SanctionsCompanion.insert(
                id: id,
                etatSync: etatSync,
                eleveId: eleveId,
                classeId: classeId,
                trimestreId: trimestreId,
                type: type,
                dureeJours: dureeJours,
                dateDebut: dateDebut,
                dateFin: dateFin,
                motif: motif,
                commentaire: commentaire,
                dateSanction: dateSanction,
                statut: statut,
                impacteBulletin: impacteBulletin,
                enregistrePar: enregistrePar,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$SanctionsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $SanctionsTable,
      Sanction,
      $$SanctionsTableFilterComposer,
      $$SanctionsTableOrderingComposer,
      $$SanctionsTableAnnotationComposer,
      $$SanctionsTableCreateCompanionBuilder,
      $$SanctionsTableUpdateCompanionBuilder,
      (Sanction, BaseReferences<_$AppDatabase, $SanctionsTable, Sanction>),
      Sanction,
      PrefetchHooks Function()
    >;
typedef $$AnnoncesTableCreateCompanionBuilder =
    AnnoncesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      required String titre,
      Value<String?> contenu,
      Value<int?> publiePar,
      Value<String?> publieeLe,
    });
typedef $$AnnoncesTableUpdateCompanionBuilder =
    AnnoncesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<String> titre,
      Value<String?> contenu,
      Value<int?> publiePar,
      Value<String?> publieeLe,
    });

class $$AnnoncesTableFilterComposer
    extends Composer<_$AppDatabase, $AnnoncesTable> {
  $$AnnoncesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get titre => $composableBuilder(
    column: $table.titre,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get contenu => $composableBuilder(
    column: $table.contenu,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get publiePar => $composableBuilder(
    column: $table.publiePar,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get publieeLe => $composableBuilder(
    column: $table.publieeLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $$AnnoncesTableOrderingComposer
    extends Composer<_$AppDatabase, $AnnoncesTable> {
  $$AnnoncesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get titre => $composableBuilder(
    column: $table.titre,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get contenu => $composableBuilder(
    column: $table.contenu,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get publiePar => $composableBuilder(
    column: $table.publiePar,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get publieeLe => $composableBuilder(
    column: $table.publieeLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$AnnoncesTableAnnotationComposer
    extends Composer<_$AppDatabase, $AnnoncesTable> {
  $$AnnoncesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<String> get titre =>
      $composableBuilder(column: $table.titre, builder: (column) => column);

  GeneratedColumn<String> get contenu =>
      $composableBuilder(column: $table.contenu, builder: (column) => column);

  GeneratedColumn<int> get publiePar =>
      $composableBuilder(column: $table.publiePar, builder: (column) => column);

  GeneratedColumn<String> get publieeLe =>
      $composableBuilder(column: $table.publieeLe, builder: (column) => column);
}

class $$AnnoncesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $AnnoncesTable,
          Annonce,
          $$AnnoncesTableFilterComposer,
          $$AnnoncesTableOrderingComposer,
          $$AnnoncesTableAnnotationComposer,
          $$AnnoncesTableCreateCompanionBuilder,
          $$AnnoncesTableUpdateCompanionBuilder,
          (Annonce, BaseReferences<_$AppDatabase, $AnnoncesTable, Annonce>),
          Annonce,
          PrefetchHooks Function()
        > {
  $$AnnoncesTableTableManager(_$AppDatabase db, $AnnoncesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$AnnoncesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$AnnoncesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$AnnoncesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<String> titre = const Value.absent(),
                Value<String?> contenu = const Value.absent(),
                Value<int?> publiePar = const Value.absent(),
                Value<String?> publieeLe = const Value.absent(),
              }) => AnnoncesCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                titre: titre,
                contenu: contenu,
                publiePar: publiePar,
                publieeLe: publieeLe,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                required String titre,
                Value<String?> contenu = const Value.absent(),
                Value<int?> publiePar = const Value.absent(),
                Value<String?> publieeLe = const Value.absent(),
              }) => AnnoncesCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                titre: titre,
                contenu: contenu,
                publiePar: publiePar,
                publieeLe: publieeLe,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$AnnoncesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $AnnoncesTable,
      Annonce,
      $$AnnoncesTableFilterComposer,
      $$AnnoncesTableOrderingComposer,
      $$AnnoncesTableAnnotationComposer,
      $$AnnoncesTableCreateCompanionBuilder,
      $$AnnoncesTableUpdateCompanionBuilder,
      (Annonce, BaseReferences<_$AppDatabase, $AnnoncesTable, Annonce>),
      Annonce,
      PrefetchHooks Function()
    >;
typedef $$NotificationsInternesTableCreateCompanionBuilder =
    NotificationsInternesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      required int schoolId,
      required int userId,
      Value<String?> type,
      required String titre,
      Value<String?> message,
      Value<String?> lien,
      Value<bool> lu,
      Value<String?> luLe,
    });
typedef $$NotificationsInternesTableUpdateCompanionBuilder =
    NotificationsInternesCompanion Function({
      Value<int> id,
      Value<String> etatSync,
      Value<int> schoolId,
      Value<int> userId,
      Value<String?> type,
      Value<String> titre,
      Value<String?> message,
      Value<String?> lien,
      Value<bool> lu,
      Value<String?> luLe,
    });

class $$NotificationsInternesTableFilterComposer
    extends Composer<_$AppDatabase, $NotificationsInternesTable> {
  $$NotificationsInternesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get titre => $composableBuilder(
    column: $table.titre,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get message => $composableBuilder(
    column: $table.message,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lien => $composableBuilder(
    column: $table.lien,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get lu => $composableBuilder(
    column: $table.lu,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get luLe => $composableBuilder(
    column: $table.luLe,
    builder: (column) => ColumnFilters(column),
  );
}

class $$NotificationsInternesTableOrderingComposer
    extends Composer<_$AppDatabase, $NotificationsInternesTable> {
  $$NotificationsInternesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get etatSync => $composableBuilder(
    column: $table.etatSync,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get schoolId => $composableBuilder(
    column: $table.schoolId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get titre => $composableBuilder(
    column: $table.titre,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get message => $composableBuilder(
    column: $table.message,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lien => $composableBuilder(
    column: $table.lien,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get lu => $composableBuilder(
    column: $table.lu,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get luLe => $composableBuilder(
    column: $table.luLe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$NotificationsInternesTableAnnotationComposer
    extends Composer<_$AppDatabase, $NotificationsInternesTable> {
  $$NotificationsInternesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get etatSync =>
      $composableBuilder(column: $table.etatSync, builder: (column) => column);

  GeneratedColumn<int> get schoolId =>
      $composableBuilder(column: $table.schoolId, builder: (column) => column);

  GeneratedColumn<int> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get type =>
      $composableBuilder(column: $table.type, builder: (column) => column);

  GeneratedColumn<String> get titre =>
      $composableBuilder(column: $table.titre, builder: (column) => column);

  GeneratedColumn<String> get message =>
      $composableBuilder(column: $table.message, builder: (column) => column);

  GeneratedColumn<String> get lien =>
      $composableBuilder(column: $table.lien, builder: (column) => column);

  GeneratedColumn<bool> get lu =>
      $composableBuilder(column: $table.lu, builder: (column) => column);

  GeneratedColumn<String> get luLe =>
      $composableBuilder(column: $table.luLe, builder: (column) => column);
}

class $$NotificationsInternesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $NotificationsInternesTable,
          NotificationsInterne,
          $$NotificationsInternesTableFilterComposer,
          $$NotificationsInternesTableOrderingComposer,
          $$NotificationsInternesTableAnnotationComposer,
          $$NotificationsInternesTableCreateCompanionBuilder,
          $$NotificationsInternesTableUpdateCompanionBuilder,
          (
            NotificationsInterne,
            BaseReferences<
              _$AppDatabase,
              $NotificationsInternesTable,
              NotificationsInterne
            >,
          ),
          NotificationsInterne,
          PrefetchHooks Function()
        > {
  $$NotificationsInternesTableTableManager(
    _$AppDatabase db,
    $NotificationsInternesTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$NotificationsInternesTableFilterComposer(
                $db: db,
                $table: table,
              ),
          createOrderingComposer: () =>
              $$NotificationsInternesTableOrderingComposer(
                $db: db,
                $table: table,
              ),
          createComputedFieldComposer: () =>
              $$NotificationsInternesTableAnnotationComposer(
                $db: db,
                $table: table,
              ),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                Value<int> schoolId = const Value.absent(),
                Value<int> userId = const Value.absent(),
                Value<String?> type = const Value.absent(),
                Value<String> titre = const Value.absent(),
                Value<String?> message = const Value.absent(),
                Value<String?> lien = const Value.absent(),
                Value<bool> lu = const Value.absent(),
                Value<String?> luLe = const Value.absent(),
              }) => NotificationsInternesCompanion(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                userId: userId,
                type: type,
                titre: titre,
                message: message,
                lien: lien,
                lu: lu,
                luLe: luLe,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> etatSync = const Value.absent(),
                required int schoolId,
                required int userId,
                Value<String?> type = const Value.absent(),
                required String titre,
                Value<String?> message = const Value.absent(),
                Value<String?> lien = const Value.absent(),
                Value<bool> lu = const Value.absent(),
                Value<String?> luLe = const Value.absent(),
              }) => NotificationsInternesCompanion.insert(
                id: id,
                etatSync: etatSync,
                schoolId: schoolId,
                userId: userId,
                type: type,
                titre: titre,
                message: message,
                lien: lien,
                lu: lu,
                luLe: luLe,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$NotificationsInternesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $NotificationsInternesTable,
      NotificationsInterne,
      $$NotificationsInternesTableFilterComposer,
      $$NotificationsInternesTableOrderingComposer,
      $$NotificationsInternesTableAnnotationComposer,
      $$NotificationsInternesTableCreateCompanionBuilder,
      $$NotificationsInternesTableUpdateCompanionBuilder,
      (
        NotificationsInterne,
        BaseReferences<
          _$AppDatabase,
          $NotificationsInternesTable,
          NotificationsInterne
        >,
      ),
      NotificationsInterne,
      PrefetchHooks Function()
    >;
typedef $$OutboxOperationsTableCreateCompanionBuilder =
    OutboxOperationsCompanion Function({
      required String id,
      required String methode,
      required String chemin,
      required String corps,
      Value<String?> entite,
      Value<int?> entiteId,
      Value<int> tentatives,
      Value<String?> derniereErreur,
      required DateTime creeLe,
      Value<DateTime?> prochainEssai,
      Value<int> rowid,
    });
typedef $$OutboxOperationsTableUpdateCompanionBuilder =
    OutboxOperationsCompanion Function({
      Value<String> id,
      Value<String> methode,
      Value<String> chemin,
      Value<String> corps,
      Value<String?> entite,
      Value<int?> entiteId,
      Value<int> tentatives,
      Value<String?> derniereErreur,
      Value<DateTime> creeLe,
      Value<DateTime?> prochainEssai,
      Value<int> rowid,
    });

class $$OutboxOperationsTableFilterComposer
    extends Composer<_$AppDatabase, $OutboxOperationsTable> {
  $$OutboxOperationsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get methode => $composableBuilder(
    column: $table.methode,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chemin => $composableBuilder(
    column: $table.chemin,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get corps => $composableBuilder(
    column: $table.corps,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get entite => $composableBuilder(
    column: $table.entite,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get entiteId => $composableBuilder(
    column: $table.entiteId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get tentatives => $composableBuilder(
    column: $table.tentatives,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get derniereErreur => $composableBuilder(
    column: $table.derniereErreur,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get creeLe => $composableBuilder(
    column: $table.creeLe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get prochainEssai => $composableBuilder(
    column: $table.prochainEssai,
    builder: (column) => ColumnFilters(column),
  );
}

class $$OutboxOperationsTableOrderingComposer
    extends Composer<_$AppDatabase, $OutboxOperationsTable> {
  $$OutboxOperationsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get methode => $composableBuilder(
    column: $table.methode,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chemin => $composableBuilder(
    column: $table.chemin,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get corps => $composableBuilder(
    column: $table.corps,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get entite => $composableBuilder(
    column: $table.entite,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get entiteId => $composableBuilder(
    column: $table.entiteId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get tentatives => $composableBuilder(
    column: $table.tentatives,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get derniereErreur => $composableBuilder(
    column: $table.derniereErreur,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get creeLe => $composableBuilder(
    column: $table.creeLe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get prochainEssai => $composableBuilder(
    column: $table.prochainEssai,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$OutboxOperationsTableAnnotationComposer
    extends Composer<_$AppDatabase, $OutboxOperationsTable> {
  $$OutboxOperationsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get methode =>
      $composableBuilder(column: $table.methode, builder: (column) => column);

  GeneratedColumn<String> get chemin =>
      $composableBuilder(column: $table.chemin, builder: (column) => column);

  GeneratedColumn<String> get corps =>
      $composableBuilder(column: $table.corps, builder: (column) => column);

  GeneratedColumn<String> get entite =>
      $composableBuilder(column: $table.entite, builder: (column) => column);

  GeneratedColumn<int> get entiteId =>
      $composableBuilder(column: $table.entiteId, builder: (column) => column);

  GeneratedColumn<int> get tentatives => $composableBuilder(
    column: $table.tentatives,
    builder: (column) => column,
  );

  GeneratedColumn<String> get derniereErreur => $composableBuilder(
    column: $table.derniereErreur,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get creeLe =>
      $composableBuilder(column: $table.creeLe, builder: (column) => column);

  GeneratedColumn<DateTime> get prochainEssai => $composableBuilder(
    column: $table.prochainEssai,
    builder: (column) => column,
  );
}

class $$OutboxOperationsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $OutboxOperationsTable,
          OutboxOperation,
          $$OutboxOperationsTableFilterComposer,
          $$OutboxOperationsTableOrderingComposer,
          $$OutboxOperationsTableAnnotationComposer,
          $$OutboxOperationsTableCreateCompanionBuilder,
          $$OutboxOperationsTableUpdateCompanionBuilder,
          (
            OutboxOperation,
            BaseReferences<
              _$AppDatabase,
              $OutboxOperationsTable,
              OutboxOperation
            >,
          ),
          OutboxOperation,
          PrefetchHooks Function()
        > {
  $$OutboxOperationsTableTableManager(
    _$AppDatabase db,
    $OutboxOperationsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$OutboxOperationsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$OutboxOperationsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$OutboxOperationsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> id = const Value.absent(),
                Value<String> methode = const Value.absent(),
                Value<String> chemin = const Value.absent(),
                Value<String> corps = const Value.absent(),
                Value<String?> entite = const Value.absent(),
                Value<int?> entiteId = const Value.absent(),
                Value<int> tentatives = const Value.absent(),
                Value<String?> derniereErreur = const Value.absent(),
                Value<DateTime> creeLe = const Value.absent(),
                Value<DateTime?> prochainEssai = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => OutboxOperationsCompanion(
                id: id,
                methode: methode,
                chemin: chemin,
                corps: corps,
                entite: entite,
                entiteId: entiteId,
                tentatives: tentatives,
                derniereErreur: derniereErreur,
                creeLe: creeLe,
                prochainEssai: prochainEssai,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String id,
                required String methode,
                required String chemin,
                required String corps,
                Value<String?> entite = const Value.absent(),
                Value<int?> entiteId = const Value.absent(),
                Value<int> tentatives = const Value.absent(),
                Value<String?> derniereErreur = const Value.absent(),
                required DateTime creeLe,
                Value<DateTime?> prochainEssai = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => OutboxOperationsCompanion.insert(
                id: id,
                methode: methode,
                chemin: chemin,
                corps: corps,
                entite: entite,
                entiteId: entiteId,
                tentatives: tentatives,
                derniereErreur: derniereErreur,
                creeLe: creeLe,
                prochainEssai: prochainEssai,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$OutboxOperationsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $OutboxOperationsTable,
      OutboxOperation,
      $$OutboxOperationsTableFilterComposer,
      $$OutboxOperationsTableOrderingComposer,
      $$OutboxOperationsTableAnnotationComposer,
      $$OutboxOperationsTableCreateCompanionBuilder,
      $$OutboxOperationsTableUpdateCompanionBuilder,
      (
        OutboxOperation,
        BaseReferences<_$AppDatabase, $OutboxOperationsTable, OutboxOperation>,
      ),
      OutboxOperation,
      PrefetchHooks Function()
    >;
typedef $$SyncEtatTableCreateCompanionBuilder =
    SyncEtatCompanion Function({
      required String cle,
      Value<String?> valeur,
      Value<int> rowid,
    });
typedef $$SyncEtatTableUpdateCompanionBuilder =
    SyncEtatCompanion Function({
      Value<String> cle,
      Value<String?> valeur,
      Value<int> rowid,
    });

class $$SyncEtatTableFilterComposer
    extends Composer<_$AppDatabase, $SyncEtatTable> {
  $$SyncEtatTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get valeur => $composableBuilder(
    column: $table.valeur,
    builder: (column) => ColumnFilters(column),
  );
}

class $$SyncEtatTableOrderingComposer
    extends Composer<_$AppDatabase, $SyncEtatTable> {
  $$SyncEtatTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get cle => $composableBuilder(
    column: $table.cle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get valeur => $composableBuilder(
    column: $table.valeur,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$SyncEtatTableAnnotationComposer
    extends Composer<_$AppDatabase, $SyncEtatTable> {
  $$SyncEtatTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get cle =>
      $composableBuilder(column: $table.cle, builder: (column) => column);

  GeneratedColumn<String> get valeur =>
      $composableBuilder(column: $table.valeur, builder: (column) => column);
}

class $$SyncEtatTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $SyncEtatTable,
          SyncEtatData,
          $$SyncEtatTableFilterComposer,
          $$SyncEtatTableOrderingComposer,
          $$SyncEtatTableAnnotationComposer,
          $$SyncEtatTableCreateCompanionBuilder,
          $$SyncEtatTableUpdateCompanionBuilder,
          (
            SyncEtatData,
            BaseReferences<_$AppDatabase, $SyncEtatTable, SyncEtatData>,
          ),
          SyncEtatData,
          PrefetchHooks Function()
        > {
  $$SyncEtatTableTableManager(_$AppDatabase db, $SyncEtatTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SyncEtatTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SyncEtatTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SyncEtatTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> cle = const Value.absent(),
                Value<String?> valeur = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => SyncEtatCompanion(cle: cle, valeur: valeur, rowid: rowid),
          createCompanionCallback:
              ({
                required String cle,
                Value<String?> valeur = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => SyncEtatCompanion.insert(
                cle: cle,
                valeur: valeur,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$SyncEtatTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $SyncEtatTable,
      SyncEtatData,
      $$SyncEtatTableFilterComposer,
      $$SyncEtatTableOrderingComposer,
      $$SyncEtatTableAnnotationComposer,
      $$SyncEtatTableCreateCompanionBuilder,
      $$SyncEtatTableUpdateCompanionBuilder,
      (
        SyncEtatData,
        BaseReferences<_$AppDatabase, $SyncEtatTable, SyncEtatData>,
      ),
      SyncEtatData,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$AnneeScolairesTableTableManager get anneeScolaires =>
      $$AnneeScolairesTableTableManager(_db, _db.anneeScolaires);
  $$TrimestresTableTableManager get trimestres =>
      $$TrimestresTableTableManager(_db, _db.trimestres);
  $$SequencesTableTableManager get sequences =>
      $$SequencesTableTableManager(_db, _db.sequences);
  $$NiveauxTableTableManager get niveaux =>
      $$NiveauxTableTableManager(_db, _db.niveaux);
  $$MatieresTableTableManager get matieres =>
      $$MatieresTableTableManager(_db, _db.matieres);
  $$ClassesTableTableManager get classes =>
      $$ClassesTableTableManager(_db, _db.classes);
  $$ClasseMatieresTableTableManager get classeMatieres =>
      $$ClasseMatieresTableTableManager(_db, _db.classeMatieres);
  $$EmploisDuTempsTableTableManager get emploisDuTemps =>
      $$EmploisDuTempsTableTableManager(_db, _db.emploisDuTemps);
  $$ProgressionItemsTableTableManager get progressionItems =>
      $$ProgressionItemsTableTableManager(_db, _db.progressionItems);
  $$ElevesTableTableManager get eleves =>
      $$ElevesTableTableManager(_db, _db.eleves);
  $$PersonnelsTableTableManager get personnels =>
      $$PersonnelsTableTableManager(_db, _db.personnels);
  $$SeancesTableTableManager get seances =>
      $$SeancesTableTableManager(_db, _db.seances);
  $$PresencesTableTableManager get presences =>
      $$PresencesTableTableManager(_db, _db.presences);
  $$NotesTableTableManager get notes =>
      $$NotesTableTableManager(_db, _db.notes);
  $$SanctionsTableTableManager get sanctions =>
      $$SanctionsTableTableManager(_db, _db.sanctions);
  $$AnnoncesTableTableManager get annonces =>
      $$AnnoncesTableTableManager(_db, _db.annonces);
  $$NotificationsInternesTableTableManager get notificationsInternes =>
      $$NotificationsInternesTableTableManager(_db, _db.notificationsInternes);
  $$OutboxOperationsTableTableManager get outboxOperations =>
      $$OutboxOperationsTableTableManager(_db, _db.outboxOperations);
  $$SyncEtatTableTableManager get syncEtat =>
      $$SyncEtatTableTableManager(_db, _db.syncEtat);
}

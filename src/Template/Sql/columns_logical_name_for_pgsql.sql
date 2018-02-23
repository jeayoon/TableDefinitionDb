select
	psat.relname as TABLE_NAME,
	pa.attname as COLUMN_NAME,
	pd.description as COLUMN_COMMENT
from
	 pg_stat_all_tables psat
	,pg_description     pd
	,pg_attribute       pa
where
	psat.relname='<?= $tableName;?>'
	and
	psat.relid=pd.objoid
	and
	pd.objsubid<>0
	and
	pd.objoid=pa.attrelid
	and
	pd.objsubid=pa.attnum
order by
	pd.objsubid

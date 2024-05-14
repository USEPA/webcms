
import './functions';
import chaiSorted from 'chai-sorted';
import 'cypress-audit/commands';
import './commands';

require('cypress-xpath');

chai.use(chaiSorted);
